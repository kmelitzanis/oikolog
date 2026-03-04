<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    // Admin dashboard
    public function index()
    {
        return view('admin.index');
    }

    // List users
    public function users()
    {
        $users = User::orderBy('created_at', 'desc')->get();
        return view('admin.users', compact('users'));
    }

    // Delete a user
    public function deleteUser(User $user)
    {
        // Prevent deleting last admin or self-deletion could be handled here
        $user->delete();
        return redirect()->route('admin.users');
    }

    // Categories management
    public function categories()
    {
        $categories = Category::orderBy('name')->get();
        return view('admin.categories', compact('categories'));
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:120'],
            'icon' => ['nullable','string','max:60'],
            'color_hex' => ['nullable','string','max:7'],
        ]);

        Category::create(array_merge($data, ['is_system' => false]));
        return redirect()->route('admin.categories');
    }

    public function deleteCategory(Category $category)
    {
        $category->delete();
        return redirect()->route('admin.categories');
    }
}
