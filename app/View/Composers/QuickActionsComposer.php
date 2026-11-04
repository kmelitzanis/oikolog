<?php

namespace App\View\Composers;

use App\Models\Bill;
use App\Models\Income;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * What the mobile "+" sheet offers to act on in one tap: the bills that want
 * paying now, the income that is due to come in, and the vehicles a fill-up
 * can be recorded against.
 *
 * Kept short: the sheet is a shortcut, the full lists are a tab away.
 */
class QuickActionsComposer
{
    private const LIMIT = 6;

    public function compose(View $view): void
    {
        $user = Auth::user();

        try {
            $bills = $user
                ? Bill::forUser($user)->active()
                    ->whereNotNull('next_due_date')
                    ->where('next_due_date', '<=', now()->addDays(7)->toDateString())
                    ->orderBy('next_due_date')
                    ->get()
                    ->filter(fn (Bill $bill) => $bill->needsAttention())
                    ->take(self::LIMIT)
                    ->values()
                : collect();

            $incomes = $user
                ? Income::forUser($user)->active()->whereNotNull('next_date')->orderBy('next_date')->get()
                    ->filter(fn (Income $income) => $income->canReceiveNow() && ! $income->isReceivedThisCycle())
                    ->take(self::LIMIT)
                    ->values()
                : collect();

            $vehicles = $user ? Vehicle::forUser($user)->active()->orderBy('name')->get(['id', 'name', 'type']) : collect();
        } catch (\Throwable $e) {
            // The layout also renders before migrations have run.
            [$bills, $incomes, $vehicles] = [collect(), collect(), collect()];
        }

        $view->with('quickActions', compact('bills', 'incomes', 'vehicles'));
    }
}
