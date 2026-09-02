<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Income;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;

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
            // Both counts go through status() rather than the raw dates: a paid
            // bill keeps its past due date, so isOverdue() stays true forever
            // and the badge kept counting bills that were already settled.
            'overdue_count' => $bills->filter(fn($b) => $b->status() === 'overdue')->count(),
            'due_this_week' => $bills->filter(fn($b) => $b->status() === 'soon')->count(),
        ];

        // Income summary
        $incomes = Income::forUser($user)->active()->get();
        $incomeStats = [
            'monthly_income' => round($incomes->sum(fn($i) => $i->monthlyEquivalent()), 2),
            'yearly_income' => round($incomes->sum(fn($i) => $i->monthlyEquivalent()) * 12, 2),
        ];
        $stats = array_merge($stats, $incomeStats);
        $stats['monthly_net'] = round($incomeStats['monthly_income'] - $stats['monthly_total'], 2);

        $upcoming = Bill::forUser($user)->dueWithin(30)->with('category')->orderBy('next_due_date')->take(8)->get();

        // ── Month progress ────────────────────────────────────────────────────
        // The hero answers "am I OK this month" with one line: how much of the
        // outstanding bill load is already settled. `paid` comes from real
        // payment records. Outstanding is this month's unpaid bills *plus*
        // anything already overdue — an overdue bill is still owed, and leaving
        // it out let the hero read "100% paid" while bills sat overdue.
        $billIds = $bills->pluck('id');
        $monthPaid = (float) \App\Models\Payment::whereIn('bill_id', $billIds)
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');
        // Settled bills are excluded, and what remains is counted at its
        // *remaining* balance: a part-paid bill already has its paid slice in
        // $monthPaid, so summing the full amount counted that money twice.
        $monthOutstanding = (float) $bills
            ->reject(fn($b) => $b->isSettled())
            ->filter(fn($b) => $b->status() === 'overdue'
                || ($b->next_due_date && $b->next_due_date->isSameMonth(now())))
            ->sum(fn($b) => $b->getEffectiveRemainingBalance());
        $monthLoad = $monthPaid + $monthOutstanding;

        $stats['month_paid']        = round($monthPaid, 2);
        $stats['month_outstanding'] = round($monthOutstanding, 2);
        $stats['month_paid_pct']    = $monthLoad > 0 ? (int) round($monthPaid / $monthLoad * 100) : 100;

        // Bills that need action now — overdue first, then due within a week.
        // Drawn from every active bill rather than from `$upcoming`: that list
        // is `dueWithin(30)`, so a bill overdue by more than a month never
        // appeared in it and the queue claimed "all paid" against a red count.
        $attention = $bills
            ->filter(fn($b) => $b->needsAttention())
            ->sortBy(fn($b) => $b->next_due_date)
            ->take(4)
            ->values();
        $byCategory = $bills->groupBy('category.name')
            ->map(fn($g) => round($g->sum(fn($b) => $b->monthlyEquivalent()), 2))
            ->sortDesc();

        // ── Chart data (last 12 months) ───────────────────────────────────────
        $chartMonths = [];
        $chartSpending = [];
        $chartIncome = [];

        // Build spending per month using actual payment records
        $userBillIds = Bill::forUser($user)->pluck('id');
        // Grouped in PHP rather than SQL: DATE_FORMAT is MySQL-only, so the
        // previous version made this whole page a 500 on SQLite. A year of one
        // household's payments is a handful of rows.
        $payments12 = \App\Models\Payment::whereIn('bill_id', $userBillIds)
            ->where('paid_at', '>=', now()->subMonths(11)->startOfMonth())
            ->get(['paid_at', 'amount'])
            ->groupBy(fn($p) => $p->paid_at->format('Y-m'))
            ->map(fn($group) => (float) $group->sum('amount'));

        // Build income per month using monthly equivalents (projected flat)
        $allIncomes = Income::forUser($user)->active()->get();
        $monthlyIncomeAmt = round($allIncomes->sum(fn($i) => $i->monthlyEquivalent()), 2);

        for ($i = 11; $i >= 0; $i--) {
            // Anchor to the 1st before stepping back: subMonths() from a 31st
            // overflows short months (July 31 − 5 lands in March, not February),
            // which dropped a month from the series and repeated another.
            $month = now()->startOfMonth()->subMonths($i);
            $ym = $month->format('Y-m');
            $chartMonths[] = $month->format('M y');
            $chartSpending[] = (float)($payments12[$ym] ?? 0);
            $chartIncome[] = $monthlyIncomeAmt;
        }

        $chartData = [
            'currency' => $user->currency_code ?? 'EUR',
            'months' => $chartMonths,
            'spending' => $chartSpending,
            'income' => $chartIncome,
            'by_category' => $byCategory->toArray(),
        ];

        return view('dashboard.index', compact('user', 'stats', 'upcoming', 'attention', 'byCategory', 'chartData'));
    }

    /**
     * Month overview (mockup 2b) — the month as a countdown line.
     * Reached by tapping the dashboard hero.
     */
    public function month()
    {
        $user  = Auth::user();
        $bills = Bill::forUser($user)->active()->with('category')->orderBy('next_due_date')->get();

        $billIds  = $bills->pluck('id');
        $paid     = (float) \App\Models\Payment::whereIn('bill_id', $billIds)
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');
        $outstanding = (float) $bills
            ->reject(fn($b) => $b->isSettled())
            ->filter(fn($b) => $b->status() === 'overdue'
                || ($b->next_due_date && $b->next_due_date->isSameMonth(now())))
            ->sum(fn($b) => $b->getEffectiveRemainingBalance());
        $load = $paid + $outstanding;

        $attention = $bills
            ->filter(fn($b) => $b->needsAttention())
            ->sortBy(fn($b) => $b->next_due_date)
            ->values();

        // Everything due after this month — the mockup's "then, nothing until".
        $later = $bills
            ->filter(fn($b) => $b->next_due_date && $b->next_due_date->gt(now()->endOfMonth()))
            ->sortBy(fn($b) => $b->next_due_date)
            ->take(5)
            ->values();

        $month = [
            'paid'        => round($paid, 2),
            'outstanding' => round($outstanding, 2),
            'load'        => round($load, 2),
            'pct'         => $load > 0 ? (int) round($paid / $load * 100) : 100,
            'day'         => now()->day,
            'days'        => now()->daysInMonth,
            'day_pct'     => (int) round(now()->day / now()->daysInMonth * 100),
        ];

        return view('dashboard.month', compact('user', 'month', 'attention', 'later'));
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors(['email' => 'Invalid credentials.'])->withInput();
        }

        // Two-factor users are held at the door: the password is checked
        // directly and nothing is signed in until the code clears.
        //
        // This deliberately avoids Auth::attempt() followed by Auth::logout(),
        // which is how it used to work: logout() cycles the remember token, so
        // every sign-in here silently invalidated the "remember me" cookie on
        // the user's *other* devices — the phone got signed out each time the
        // laptop signed in.
        if ($user->two_factor_enabled) {
            $request->session()->put([
                '2fa_user_id'  => $user->id,
                '2fa_remember' => $remember,
            ]);

            return redirect()->route('2fa.challenge');
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    // Show settings form
    public function settings()
    {
        $user = Auth::user();
        $avatar = $user->avatar_url ?? null;

        // The invoice-mail crawler is optional, and its table arrives with a
        // migration that may not have run yet — a deployment where migrations
        // failed must still leave profile and password reachable, so this page
        // degrades instead of 500-ing.
        $mailboxReady = \Illuminate\Support\Facades\Schema::hasTable('mailboxes');
        $mailbox = $mailboxReady
            ? \App\Models\Mailbox::where('user_id', $user->id)->first()
            : null;

        $accounts = \App\Models\Account::forUser($user)->active()->orderBy('name')->get();

        return view('settings.index', compact('user', 'avatar', 'mailbox', 'mailboxReady', 'accounts'));
    }

    // Update settings
    public function updateSettings(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', 'in:male,female'],
            'email' => ['required', 'email', 'unique:users,email,' . $user->id],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'default_account_id' => ['nullable', 'exists:accounts,id'],
            'password' => ['nullable', 'confirmed', 'min:8'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'avatar_url' => ['nullable', 'url'],
            'locale' => ['nullable', 'string'],
        ]);

        $update = [
            'name' => $data['name'],
            'gender' => $data['gender'] ?? $user->gender,
            'email' => $data['email'],
            'currency_code' => $data['currency_code'] ?? $user->currency_code,
            // Absent (an API caller) leaves it alone; blank means "no
            // preference" and the modal falls back as before.
            'default_account_id' => array_key_exists('default_account_id', $data)
                ? ($data['default_account_id'] ?: null)
                : $user->default_account_id,
            'avatar_url' => $data['avatar_url'] ?? $user->avatar_url,
            'locale' => $data['locale'] ?? $user->locale ?? 'en',
        ];

        if (!empty($data['password'])) {
            $update['password'] = $data['password'];
        }

        // Handle uploaded avatar image
        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            // If Spatie medialibrary is installed and model uses it, attach via medialibrary
            if (method_exists($user, 'addMedia')) {
                // attach single avatar (clear previous if singleFile not used)
                try {
                    $user->clearMediaCollection('avatars');
                } catch (\Throwable $e) {
                    // ignore if not supported
                }
                try {
                    $user->addMedia($file->getRealPath())->usingFileName(uniqid() . '.' . $file->getClientOriginalExtension())->toMediaCollection('avatars');
                    $update['avatar_url'] = $user->getFirstMediaUrl('avatars', 'thumb') ?: $update['avatar_url'];
                } catch (\Throwable $e) {
                    // fallthrough to fallback storage
                }
            }

            // If avatar_url still not set, fallback to Intervention resize + store
            if (empty($update['avatar_url'])) {
                if (class_exists(\Intervention\Image\ImageManagerStatic::class)) {
                    $img = Image::make($file->getRealPath())->fit(256, 256, function ($constraint) {
                        $constraint->upsize();
                    })->encode('jpg', 85);
                    $filename = 'avatars/' . uniqid() . '.jpg';
                    Storage::disk('public')->put($filename, (string)$img);
                    $update['avatar_url'] = Storage::disk('public')->url($filename);
                } else {
                    // Fallback: store original file if imagemagick/gd not available
                    $path = $file->store('avatars', 'public');
                    $update['avatar_url'] = Storage::disk('public')->url($path);
                }
            }
        }

        $user->update($update);
        $user->refresh();

        return back()->with('success', 'Settings updated.');
    }

    // Set locale via quick route (session + user if authenticated)
    public function setLocale($lang)
    {
        $locales = $this->availableLocales();
        if (!in_array($lang, $locales)) abort(400);
        session(['locale' => $lang]);
        \Illuminate\Support\Facades\App::setLocale($lang);
        if ($user = Auth::user()) {
            $user->update(['locale' => $lang]);
        }
        // Redirect back explicitly so a fresh request picks up the session locale
        $back = url()->previous() ?: route('dashboard');
        return redirect()->to($back)->with('success', __('Settings updated.'));
    }

    private function availableLocales(): array
    {
        $langPath = resource_path('lang');
        $locales = [];
        if (is_dir($langPath)) {
            foreach (scandir($langPath) as $entry) {
                if (in_array($entry, ['.', '..'])) continue;
                $full = $langPath . DIRECTORY_SEPARATOR . $entry;
                if (is_file($full) && pathinfo($full, PATHINFO_EXTENSION) === 'json') {
                    $locales[] = pathinfo($full, PATHINFO_FILENAME);
                } elseif (is_dir($full)) {
                    $locales[] = $entry;
                }
            }
        }
        return array_values(array_unique($locales));
    }
}
