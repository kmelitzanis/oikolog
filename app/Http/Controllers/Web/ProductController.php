<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ShoppingListItem;
use App\Services\ProductCatalog;
use Illuminate\Http\Request;

/**
 * The product library: everything that has ever been scanned or entered, and
 * one page per product. The catalogue is shared, so there is no ownership
 * check — only deleting is gated, on the product having no history.
 */
class ProductController extends Controller
{
    public function __construct(private ProductCatalog $catalog) {}

    public function index(Request $request)
    {
        $query = Product::query()
            ->withCount('purchases')
            ->withMax('purchases as last_purchased_at', 'purchased_at');

        if ($request->filled('search')) {
            $query->search($request->string('search')->toString());
        }
        if ($request->filled('grade')) {
            $query->grade($request->string('grade')->toString());
        }
        match ($request->input('sort')) {
            'bought' => $query->orderByDesc('purchases_count'),
            // Nulls last, so never-bought products don't crowd the top.
            'recent' => $query->orderByRaw('last_purchased_at IS NULL')->orderByDesc('last_purchased_at'),
            default => $query->orderBy('name'),
        };

        $products = $query->paginate(48)->withQueryString();

        $stats = [
            'total' => Product::count(),
            'scanned' => Product::where('source', Product::SOURCE_API)->count(),
            'scored' => Product::whereNotNull('nutri_score')->count(),
            // How the basket splits across the score — the one number that says
            // something about how you actually shop.
            'grades' => collect(Product::GRADES)
                ->mapWithKeys(fn($g) => [$g => Product::where('nutri_score', $g)->count()])
                ->all(),
        ];

        return view('products.index', compact('products', 'stats'));
    }

    public function create()
    {
        return view('products.form');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $product = Product::create($data + [
            'source' => Product::SOURCE_MANUAL,
            'is_edited' => true,
            'user_id' => $request->user()->id,
        ]);

        return redirect()->route('products.show', $product)->with('success', __('messages.product_created'));
    }

    public function show(Product $product)
    {
        $product->loadCount('purchases');

        $purchases = $product->purchases()->with('buyer:id,name', 'shoppingList:id,name')
            ->orderByDesc('purchased_at')->limit(30)->get();

        // "On a list right now" — the unticked lines pointing here.
        $openItems = $product->listItems()->with('shoppingList:id,name')
            ->where('checked', false)->get();

        $rhythm = [
            'every' => $product->averageDaysBetweenPurchases(),
            'next' => $product->expectedNextPurchase(),
        ];

        return view('products.show', compact('product', 'purchases', 'openItems', 'rhythm'));
    }

    public function edit(Product $product)
    {
        return view('products.form', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        // Any hand edit marks the product, so a later API refresh won't undo it.
        $product->update($this->validated($request, $product) + ['is_edited' => true]);

        return redirect()->route('products.show', $product)->with('success', __('messages.product_updated'));
    }

    public function destroy(Product $product)
    {
        // A product with history is what that history refers to; deleting it
        // would erase purchases, so it is refused rather than cascaded.
        if ($product->purchases()->exists()) {
            return back()->with('error', __('messages.product_has_purchases'));
        }

        $product->delete();

        return redirect()->route('products.index')->with('success', __('messages.product_deleted'));
    }

    /** Pull the product's details from its barcode again. */
    public function refresh(Product $product)
    {
        if (!$product->isRefreshable()) {
            return back()->with('error', __('messages.product_no_barcode'));
        }

        return $this->catalog->refreshFromApi($product)
            ? back()->with('success', __('messages.product_refreshed'))
            : back()->with('error', __('messages.product_refresh_failed'));
    }

    /** Give a hand-typed shopping list line a product page of its own. */
    public function promote(Request $request, ShoppingListItem $item)
    {
        abort_unless($item->shoppingList?->user_id === $request->user()->id, 403);

        if ($item->product_id) {
            return redirect()->route('products.show', $item->product_id);
        }

        $product = $this->catalog->createFromItem($item, $request->user());

        return redirect()->route('products.show', $product)->with('success', __('messages.product_created'));
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:100'],
            'barcode' => ['nullable', 'string', 'max:50', 'unique:products,barcode' . ($product ? ',' . $product->id : '')],
            'category' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:50'],
            'default_quantity' => ['nullable', 'numeric', 'min:0'],
            'net_quantity' => ['nullable', 'string', 'max:60'],
            'serving_size' => ['nullable', 'string', 'max:60'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'description' => ['nullable', 'string'],
            'ingredients_text' => ['nullable', 'string'],
            'nutri_score' => ['nullable', 'in:a,b,c,d,e'],
            'eco_score' => ['nullable', 'in:a,b,c,d,e'],
            'nova_group' => ['nullable', 'integer', 'min:1', 'max:4'],
            'nutrition' => ['nullable', 'array'],
            'nutrition.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Fields the request omitted are simply absent from the validated set.
        $data['unit'] = ($data['unit'] ?? null) ?: 'piece';
        $data['default_quantity'] = $data['default_quantity'] ?? 1;
        $data['barcode'] = ($data['barcode'] ?? null) ?: null;
        // Empty boxes should clear the value, not store a table full of zeroes.
        $data['nutrition'] = array_filter(
            $data['nutrition'] ?? [],
            fn($v) => $v !== null && $v !== '',
        ) ?: null;

        return $data;
    }
}
