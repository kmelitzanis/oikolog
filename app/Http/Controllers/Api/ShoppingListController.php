<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Services\NutritionApiService;
use App\Services\ProductCatalog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ShoppingListController extends Controller
{
    use AuthorizesRequests;

    protected NutritionApiService $nutritionService;

    public function __construct(
        NutritionApiService $nutritionService,
        private ProductCatalog $catalog,
    ) {
        $this->nutritionService = $nutritionService;
    }

    public function index(Request $request): JsonResponse
    {
        $lists = ShoppingList::where('user_id', $request->user()->id)
            ->withCount([
                'items',
                'items as checked_items_count' => fn ($q) => $q->where('checked', true),
            ])
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%' . $request->search . '%'))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($lists);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $validated['user_id'] = $request->user()->id;

        $list = ShoppingList::create($validated);

        return response()->json($list, 201);
    }

    public function show(ShoppingList $list): JsonResponse
    {
        $this->authorize('view', $list);

        $list->load('items.product');

        return response()->json($list);
    }

    public function update(Request $request, ShoppingList $list): JsonResponse
    {
        $this->authorize('update', $list);

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_completed' => 'boolean',
        ]);

        $list->update($validated);

        return response()->json($list);
    }

    public function destroy(Request $request, ShoppingList $list): JsonResponse
    {
        $this->authorize('delete', $list);

        $list->delete();

        return response()->json(null, 204);
    }

    /**
     * Add item to shopping list
     */
    public function addItem(Request $request, ShoppingList $list): JsonResponse
    {
        $this->authorize('update', $list);

        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'quantity' => 'nullable|numeric|min:0.1',
            'unit'     => 'nullable|string|max:50',
            'barcode'  => 'nullable|string|max:50',
            'product_id' => 'nullable|exists:products,id',
        ]);

        $validated['shopping_list_id'] = $list->id;
        $validated['quantity'] ??= 1;
        $validated['unit'] ??= 'piece';

        // A barcode identifies a catalogue entry: reuse it, or create it from
        // the API the first time it is seen. Failing that the line stays free
        // text rather than blocking the add.
        if (empty($validated['product_id']) && !empty($validated['barcode'])) {
            $product = $this->catalog->findOrCreateByBarcode($validated['barcode'], $request->user());
            $validated['product_id'] = $product?->id;
        }

        $item = ShoppingListItem::create($validated);

        // Anything typed by hand joins the catalogue too, matched by name so
        // "milk" written twice is one product with one history — that is what
        // makes the suggestions while typing worth anything.
        if (!$item->product_id) {
            $this->catalog->linkByName($item, $request->user());
        }

        return response()->json($item->load('product'), 201);
    }

    /**
     * Update item in shopping list
     */
    public function updateItem(Request $request, ShoppingList $list, ShoppingListItem $item): JsonResponse
    {
        $this->authorize('update', $list);

        if ($item->shopping_list_id !== $list->id) {
            return response()->json(['message' => 'Item does not belong to this list'], 422);
        }

        $validated = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'quantity' => 'nullable|numeric|min:0.1',
            'unit'     => 'nullable|string|max:50',
            'checked'  => 'sometimes|boolean',
        ]);

        if (array_key_exists('checked', $validated)) {
            $validated['checked_at'] = $validated['checked'] ? now() : null;
        }

        $item->update($validated);

        return response()->json($item);
    }

    /**
     * Remove item from shopping list
     */
    public function removeItem(Request $request, ShoppingList $list, ShoppingListItem $item): JsonResponse
    {
        $this->authorize('update', $list);

        if ($item->shopping_list_id !== $list->id) {
            return response()->json(['message' => 'Item does not belong to this list'], 422);
        }

        $item->delete();

        return response()->json(null, 204);
    }

    /**
     * Toggle item checked status
     */
    public function toggleItem(Request $request, ShoppingList $list, ShoppingListItem $item): JsonResponse
    {
        $this->authorize('update', $list);

        if ($item->shopping_list_id !== $list->id) {
            return response()->json(['message' => 'Item does not belong to this list'], 422);
        }

        $checked = ! $item->checked;
        $item->update([
            'checked' => $checked,
            // The timestamp is what lets progress describe this trip rather
            // than every tick ever made on the list.
            'checked_at' => $checked ? now() : null,
        ]);

        // Ticking an item is the moment it was bought; un-ticking takes it back.
        $item->checked
            ? $this->catalog->recordPurchase($item, $request->user())
            : $this->catalog->undoPurchase($item);

        return response()->json($item->load('product'));
    }

    /**
     * Lookup product by barcode and get nutrition data
     */
    public function lookupBarcode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => 'required|string|max:50',
        ]);

        try {
            $data = $this->nutritionService->lookupByBarcode($validated['barcode']);

            if (!$data) {
                return response()->json(['message' => 'Product not found'], 404);
            }

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error looking up barcode',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
