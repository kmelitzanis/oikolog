<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Income;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IncomeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Income::forUser($user)->orderByDesc('next_date');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('frequency')) {
            $query->where('frequency', $request->frequency);
        }
        if ($request->filled('status')) {
            match ($request->status) {
                'active' => $query->where('is_active', true),
                'inactive' => $query->where('is_active', false),
                default => null,
            };
        }

        $incomes = $query->paginate(50);

        // Summary stats
        $allActive = Income::forUser($user)->active()->get();
        $monthlyIncome = round($allActive->sum(fn($i) => $i->monthlyEquivalent()), 2);

        // "Received this month" — there is no income-payments table, so this is
        // derived from `last_received_date`: a source counts once its most
        // recent receipt falls inside the current month.
        $receivedSources = $allActive->filter(
            fn($i) => $i->last_received_date && $i->last_received_date->isSameMonth(now())
        );
        $received = round($receivedSources->sum('amount'), 2);

        // Bill payments funded from these sources reduce what is still
        // available — the totals must reflect that, not just the gross amounts.
        $totalSpent = round($allActive->sum(fn($i) => $i->spentThisPeriod()), 2);
        $totalGross = round((float) $allActive->sum('amount'), 2);

        $stats = [
            'monthly_income' => $monthlyIncome,
            'total_gross' => $totalGross,
            'total_spent' => $totalSpent,
            'total_remaining' => round($totalGross - $totalSpent, 2),
            'yearly_income' => round($monthlyIncome * 12, 2),
            'total_sources' => $allActive->count(),
            'recurring' => $allActive->filter(fn($i) => $i->frequency !== 'once')->count(),
            'received_this_month' => $received,
            'received_count' => $receivedSources->count(),
            'received_pct' => $monthlyIncome > 0
                ? min(100, (int) round($received / $monthlyIncome * 100))
                : 0,
        ];

        return view('income.index', compact('incomes', 'stats'));
    }

    public function create()
    {
        return view('income.form');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'source' => ['nullable', 'string', 'max:80'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'frequency' => ['required', 'in:once,daily,weekly,biweekly,monthly,quarterly,yearly'],
            'frequency_interval' => ['nullable', 'integer', 'min:1', 'max:99'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'is_shared' => ['nullable'],
            'notes' => ['nullable', 'string'],
        ]);

        Income::create([
            ...$data,
            'currency_code' => $request->user()->currency_code,
            'is_shared' => (bool)($data['is_shared'] ?? false),
            'created_by' => $request->user()->id,
            'family_id' => ($data['is_shared'] ?? false) ? $request->user()->family_id : null,
            'next_date' => $data['start_date'],
            'frequency_interval' => $data['frequency_interval'] ?? 1,
        ]);

        return redirect()->route('income.index')->with('success', 'Income source added.');
    }

    public function show(Income $income)
    {
        $this->authorizeAccess($income);

        $periodStart = $income->currentPeriodStart();
        $periodEnd   = $income->currentPeriodEnd();

        $query = $income->payments()
            ->with(['bill:id,name,category_id', 'bill.category:id,name,icon,color_hex', 'paidBy:id,name'])
            ->whereNotNull('paid_at')
            ->orderByDesc('paid_at');

        if ($periodStart) {
            $query->where('paid_at', '>=', $periodStart->copy()->startOfDay());
            if ($periodEnd) $query->where('paid_at', '<', $periodEnd->copy()->startOfDay());
        }

        $periodPayments = $query->get();
        $spent          = round((float) $periodPayments->sum('amount'), 2);
        $remaining      = round((float) $income->amount - $spent, 2);
        $spentPercent   = (float) $income->amount > 0
            ? (int) min(100, round($spent / (float) $income->amount * 100))
            : 0;

        return view('income.show', compact(
            'income', 'periodStart', 'periodEnd', 'periodPayments', 'spent', 'remaining', 'spentPercent'
        ));
    }

    public function edit(Income $income)
    {
        $this->authorizeAccess($income);
        return view('income.form', compact('income'));
    }

    public function update(Request $request, Income $income)
    {
        $this->authorizeAccess($income);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'source' => ['nullable', 'string', 'max:80'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'frequency' => ['required', 'in:once,daily,weekly,biweekly,monthly,quarterly,yearly'],
            'frequency_interval' => ['nullable', 'integer', 'min:1', 'max:99'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
            'is_shared' => ['nullable'],
            'is_active' => ['nullable'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['is_shared'] = (bool)($data['is_shared'] ?? false);
        $data['is_active'] = (bool)($data['is_active'] ?? true);
        $data['family_id'] = $data['is_shared'] ? $request->user()->family_id : null;
        $data['frequency_interval'] = $data['frequency_interval'] ?? 1;

        $income->update($data);

        return redirect()->route('income.show', $income)->with('success', 'Income updated.');
    }

    public function destroy(Income $income)
    {
        $this->authorizeAccess($income);
        $income->delete();

        return redirect()->route('income.index')->with('success', 'Income deleted.');
    }

    /** Mark the income as received today and advance next_date */
    public function markReceived(Request $request, Income $income)
    {
        $this->authorizeAccess($income);

        $nextDate = $income->calculateNextDate();
        $income->update([
            'last_received_date' => now()->toDateString(),
            'next_date' => $nextDate ? $nextDate->toDateString() : $income->next_date,
        ]);

        return back()->with('success', 'Income marked as received.');
    }

    private function authorizeAccess(Income $income): void
    {
        $user = Auth::user();
        $ok = $income->created_by === $user->id
            || ($income->is_shared && $income->family_id === $user->family_id);
        abort_unless($ok, 403, 'Access denied.');
    }
}

