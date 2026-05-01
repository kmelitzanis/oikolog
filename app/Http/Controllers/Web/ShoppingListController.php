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

        $list->load('items');

        return view('shopping-list.show', compact('list'));
    }
}
