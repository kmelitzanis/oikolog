<?php

namespace App\Support;

use App\Models\AccountTransaction;
use App\Models\Bill;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Where the money went, and came from, over a period of whole months.
 *
 * Spending is what was really paid: bill payments, purchases and loose
 * vehicle costs (a recurring vehicle cost is a bill, so it is counted once,
 * as a payment). Income is what was really received: deposits of an income
 * into an account. An income marked received without an account leaves no
 * amount behind, so it cannot be counted.
 *
 * Grouped in PHP rather than SQL, like the dashboard: date functions differ
 * between MySQL and SQLite, and a household's year is a few hundred rows.
 */
class HouseholdReport
{
    public readonly CarbonImmutable $from;
    public readonly CarbonImmutable $to;

    /** @var Collection<int, Payment> */
    public readonly Collection $payments;
    /** @var Collection<int, Expense> */
    public readonly Collection $purchases;
    /** @var Collection<int, VehicleExpense> */
    public readonly Collection $vehicleCosts;
    /** @var Collection<int, AccountTransaction> */
    public readonly Collection $incomes;

    /**
     * `$period` is '6m', '12m', a year, or 'custom' with `$from` and `$to`
     * (any two days; the months they touch are the report's months).
     */
    public function __construct(
        public readonly User $user,
        public readonly string $period,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
    ) {
        [$this->from, $this->to] = $period === 'custom' && $from && $to
            ? [$from->startOfDay(), $to->startOfDay()]
            : self::range($period);
        $between = [$this->from, $this->to->endOfDay()];

        $this->payments = Payment::whereIn('bill_id', Bill::forUser($user)->select('id'))
            ->whereBetween('paid_at', $between)
            ->with(['bill.category', 'paidBy', 'account'])
            ->orderBy('paid_at')->get();

        $this->purchases = Expense::forUser($user)
            ->whereBetween('spent_at', $between)
            ->with('account')
            ->orderBy('spent_at')->get();

        $this->vehicleCosts = VehicleExpense::whereIn('vehicle_id', Vehicle::forUser($user)->select('id'))
            ->whereBetween('spent_at', [$this->from->toDateString(), $this->to->toDateString()])
            ->with('vehicle')
            ->orderBy('spent_at')->get();

        $this->incomes = AccountTransaction::whereIn('income_id', Income::forUser($user)->select('id'))
            ->where('direction', 1)
            ->whereBetween('occurred_at', $between)
            ->with(['income', 'account'])
            ->orderBy('occurred_at')->get();
    }

    /** The periods offered: the last 6 and 12 months, then every year with data. */
    public static function periods(User $user): array
    {
        $first = collect([
            Payment::whereIn('bill_id', Bill::forUser($user)->select('id'))->min('paid_at'),
            Expense::forUser($user)->min('spent_at'),
            VehicleExpense::whereIn('vehicle_id', Vehicle::forUser($user)->select('id'))->min('spent_at'),
        ])->filter()->map(fn ($d) => CarbonImmutable::parse($d)->year)->min() ?? now()->year;

        $periods = ['6m' => __('messages.reports_last_months', ['count' => 6]), '12m' => __('messages.reports_last_months', ['count' => 12])];
        for ($y = now()->year; $y >= $first; $y--) {
            $periods[(string) $y] = (string) $y;
        }

        return $periods;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} first and last day */
    public static function range(string $period): array
    {
        $now = CarbonImmutable::now();
        if (preg_match('/^(\d+)m$/', $period, $m)) {
            return [$now->startOfMonth()->subMonths((int) $m[1] - 1), $now->endOfMonth()->startOfDay()];
        }
        $year = (int) $period;

        return [CarbonImmutable::create($year, 1, 1), CarbonImmutable::create($year, 12, 31)];
    }

    public static function isValidPeriod(string $period): bool
    {
        return (bool) preg_match('/^(6m|12m|custom|(19|20)\d\d)$/', $period);
    }

    /** @return array<string, CarbonImmutable> 'Y-m' => first of the month */
    public function months(): array
    {
        $months = [];
        for ($m = $this->from->startOfMonth(); $m->lte($this->to); $m = $m->addMonth()) {
            $months[$m->format('Y-m')] = $m;
        }

        return $months;
    }

    /**
     * One row per month.
     *
     * @return array<string, array{month: CarbonImmutable, bills: float, purchases: float, vehicles: float, spent: float, income: float, net: float}>
     */
    public function monthly(): array
    {
        $sum = fn (Collection $rows, string $dateField) => $rows
            ->groupBy(fn ($r) => $r->{$dateField}->format('Y-m'))
            ->map(fn ($g) => (float) $g->sum('amount'));

        $bills = $sum($this->payments, 'paid_at');
        $purchases = $sum($this->purchases, 'spent_at');
        $vehicles = $sum($this->vehicleCosts, 'spent_at');
        $income = $sum($this->incomes, 'occurred_at');

        $rows = [];
        foreach ($this->months() as $ym => $month) {
            $spent = ($bills[$ym] ?? 0) + ($purchases[$ym] ?? 0) + ($vehicles[$ym] ?? 0);
            $rows[$ym] = [
                'month'     => $month,
                'bills'     => round($bills[$ym] ?? 0, 2),
                'purchases' => round($purchases[$ym] ?? 0, 2),
                'vehicles'  => round($vehicles[$ym] ?? 0, 2),
                'spent'     => round($spent, 2),
                'income'    => round($income[$ym] ?? 0, 2),
                'net'       => round(($income[$ym] ?? 0) - $spent, 2),
            ];
        }

        return $rows;
    }

    /** @return array{spent: float, income: float, net: float, bills: float, purchases: float, vehicles: float, average: float, savings_rate: ?int} */
    public function totals(): array
    {
        $bills = (float) $this->payments->sum('amount');
        $purchases = (float) $this->purchases->sum('amount');
        $vehicles = (float) $this->vehicleCosts->sum('amount');
        $income = (float) $this->incomes->sum('amount');
        $spent = $bills + $purchases + $vehicles;

        // Average over the months that have begun: the current month counts,
        // months still to come in a chosen year do not.
        $elapsed = collect($this->months())->filter(fn ($m) => $m->lte(now()))->count() ?: 1;

        return [
            'spent'        => round($spent, 2),
            'income'       => round($income, 2),
            'net'          => round($income - $spent, 2),
            'bills'        => round($bills, 2),
            'purchases'    => round($purchases, 2),
            'vehicles'     => round($vehicles, 2),
            'average'      => round($spent / $elapsed, 2),
            'savings_rate' => $income > 0 ? (int) round(($income - $spent) / $income * 100) : null,
        ];
    }

    /**
     * Spending by category, largest first. Bills by their category, purchases
     * and vehicle costs by theirs.
     *
     * @return Collection<int, array{label: string, source: string, amount: float}>
     */
    public function byCategory(): Collection
    {
        $rows = collect();
        foreach ($this->payments->groupBy(fn ($p) => $p->bill?->category?->name ?? __('messages.reports_uncategorised')) as $label => $g) {
            $rows->push(['label' => $label, 'source' => __('messages.bills'), 'amount' => (float) $g->sum('amount')]);
        }
        foreach ($this->purchases->groupBy('category') as $key => $g) {
            $rows->push(['label' => __('messages.expense_category_' . $key), 'source' => __('messages.expenses_menu'), 'amount' => (float) $g->sum('amount')]);
        }
        foreach ($this->vehicleCosts->groupBy('category') as $key => $g) {
            $rows->push(['label' => __('messages.vehicle_expense_' . $key), 'source' => __('messages.vehicles'), 'amount' => (float) $g->sum('amount')]);
        }

        return $rows->map(fn ($r) => [...$r, 'amount' => round($r['amount'], 2)])->sortByDesc('amount')->values();
    }

    /** @return Collection<string, float> bill name => paid, largest first */
    public function topBills(int $limit = 8): Collection
    {
        return $this->payments
            ->groupBy(fn ($p) => $p->bill?->name ?? '—')
            ->map(fn ($g) => round((float) $g->sum('amount'), 2))
            ->sortDesc()->take($limit);
    }

    /** @return Collection<string, float> income name => received, largest first */
    public function incomeBySource(): Collection
    {
        return $this->incomes
            ->groupBy(fn ($t) => $t->income?->name ?? $t->description ?? '—')
            ->map(fn ($g) => round((float) $g->sum('amount'), 2))
            ->sortDesc();
    }

    public function label(): string
    {
        if ($this->period === 'custom') {
            return __('messages.reports_range', ['from' => $this->from->format('d/m/Y'), 'to' => $this->to->format('d/m/Y')]);
        }

        return self::periods($this->user)[$this->period] ?? $this->period;
    }

    /** The query string that brings this period back: for links and the export. */
    public function query(): array
    {
        return $this->period === 'custom'
            ? ['period' => 'custom', 'from' => $this->from->toDateString(), 'to' => $this->to->toDateString()]
            : ['period' => $this->period];
    }
}
