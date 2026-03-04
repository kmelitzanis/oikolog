<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class DashboardController extends Controller
{
    public function index()
    {
        $user  = Auth::user();
        $bills = Bill::forUser($user)->active()->with('category')->orderBy('next_due_date')->get();

        $stats = [
            'monthly_total' => round($bills->sum(fn($b) => $b->monthlyEquivalent()), 2),
            'yearly_total'  => round($bills->sum(fn($b) => $b->monthlyEquivalent()) * 12, 2),
            'active_count'  => $bills->count(),
            'overdue_count' => $bills->filter(fn($b) => $b->isOverdue())->count(),
            'due_this_week' => $bills->filter(fn($b) => $b->daysUntilDue() >= 0 && $b->daysUntilDue() <= 7)->count(),
        ];

        $upcoming   = Bill::forUser($user)->dueWithin(30)->with('category')->orderBy('next_due_date')->take(8)->get();
        $byCategory = $bills->groupBy('category.name')
            ->map(fn($g) => round($g->sum(fn($b) => $b->monthlyEquivalent()), 2))
            ->sortDesc();

        return view('dashboard.index', compact('user', 'stats', 'upcoming', 'byCategory'));
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended('/');
        }

        return back()->withErrors(['email' => 'Invalid credentials.'])->withInput();
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'email'         => ['required', 'email', 'unique:users'],
            'password'      => ['required', 'confirmed', 'min:8'],
            'currency_code' => ['nullable', 'string', 'size:3'],
        ]);

        $user = User::create([
            'name'          => $data['name'],
            'email'         => $data['email'],
            'password'      => $data['password'],
            'currency_code' => $data['currency_code'] ?? 'EUR',
        ]);

        Auth::login($user);
        return redirect('/');
    }
}
