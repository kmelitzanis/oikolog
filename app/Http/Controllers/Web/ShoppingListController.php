<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ShoppingList;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class ShoppingListController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        return view('shopping-list.index');
    }

    public function show(Request $request, ShoppingList $list)
    {
        $this->authorize('view', $list);

        $list->load('items.product');

        // The mockup puts a list picker beside the open list, so `show` needs
        // every list the user can see, with enough counts to render each row's
        // "n of m" summary.
        $lists = $this->visibleListsFor($request->user());

        return view('shopping-list.show', compact('list', 'lists'));
    }

    /**
     * Lists the given user can see, with item counts for the picker.
     */
    private function visibleListsFor($user)
    {
        // Shopping lists are strictly per-user — ShoppingListPolicy::view()
        // only allows the owner, so this deliberately does not follow the
        // family sharing that bills and incomes use.
        return ShoppingList::query()
            ->where('user_id', $user->id)
            ->withCount([
                'items',
                'items as checked_items_count' => fn($q) => $q->where('checked', true),
                // What is still to buy is the number worth showing: these lists
                // stay permanently stocked and mostly ticked.
                'items as pending_items_count' => fn($q) => $q->where('checked', false),
            ])
            ->orderByDesc('updated_at')
            ->get();
    }
}
