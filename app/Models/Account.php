<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A place the user keeps money — they name and create their own; the app
 * ships none. Balance is never stored: it is the opening balance plus the
 * signed sum of the ledger, so it can't drift away from the history.
 *
 * A `budget` account is an envelope rather than a pot: it holds a fixed amount
 * per cycle (payday to payday), spending comes out of that, and each cycle
 * starts again from the full amount. Leftover money never carries over by
 * itself — it only moves when the user transfers it somewhere.
 */
class Account extends Model
{
    use HasUlids;

    protected $fillable = [
        'name', 'description', 'kind', 'cycle_amount', 'cycle_day', 'cycle_settled_until', 'icon', 'color_hex', 'opening_balance',
        'currency_code', 'is_active', 'is_shared', 'notes', 'created_by', 'family_id',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'cycle_amount' => 'decimal:2',
            'cycle_day' => 'integer',
            'cycle_settled_until' => 'datetime',
            'is_active' => 'boolean',
            'is_shared' => 'boolean',
        ];
    }

    // ── Relations ──────────────────────────────────────────────────────────────
    public function transactions(): HasMany
    {
        return $this->hasMany(AccountTransaction::class);
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────
    public function scopeForUser($query, $user)
    {
        if (!$user) return $query->whereRaw('1=0');

        return $query->where(function ($q) use ($user) {
            $q->where('created_by', $user->id)
                ->orWhere(function ($q2) use ($user) {
                    $q2->where('is_shared', true)
                        ->where('family_id', $user->family_id);
                });
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Every account the user can see, each with its balance and this month's
     * movements, plus the totals across the active ones.
     *
     * @return array{rows: \Illuminate\Support\Collection, stats: array}
     */
    public static function summaryFor($user): array
    {
        $from = now()->startOfMonth();
        $to   = now()->endOfMonth();

        $rows = static::forUser($user)->orderByDesc('is_active')->orderBy('name')->get()
            ->map(fn(self $a) => [
                'account'   => $a,
                'balance'   => $a->balance(),
                'cycle'     => $a->isBudget() ? $a->cycleSummary() : null,
                'movements' => $a->movementsBetween($from, $to),
            ]);

        $active  = $rows->filter(fn($r) => $r['account']->is_active);
        $budgets = $active->filter(fn($r) => $r['cycle'] !== null);

        return [
            'rows' => $rows,
            'stats' => [
                // Only real money counts towards the total: an envelope's
                // figure is an allowance, not something that accumulates.
                'total' => round($active->whereNull('cycle')->sum('balance'), 2),
                'budget_left'  => round($budgets->sum(fn($r) => $r['cycle']['left']), 2),
                'budget_total' => round($budgets->sum(fn($r) => $r['cycle']['available']), 2),
                'budget_count' => $budgets->count(),
                'in'    => round($active->sum(fn($r) => $r['movements']['in']), 2),
                'out'   => round($active->sum(fn($r) => $r['movements']['out']), 2),
                'count' => $active->count(),
            ],
        ];
    }

    // ── Balance ────────────────────────────────────────────────────────────────

    public function isBudget(): bool
    {
        return $this->kind === 'budget';
    }

    /**
     * The figure to show next to the account's name: what is left in the
     * current cycle for an envelope, the ledger balance for anything else.
     * Never negative for an envelope — overspending is reported separately.
     */
    public function available(): float
    {
        return $this->isBudget() ? $this->cycleSummary()['left'] : $this->balance();
    }

    /** Opening balance plus every movement recorded against this account. */
    public function balance(): float
    {
        $movements = (float) $this->transactions()
            ->selectRaw('COALESCE(SUM(amount * direction), 0) as total')
            ->value('total');

        return round((float) $this->opening_balance + $movements, 2);
    }

    /** Movements only, over the given window — used for the "this month" figures. */
    public function movementsBetween(\Carbon\Carbon $from, \Carbon\Carbon $to): array
    {
        $rows = $this->transactions()
            ->whereBetween('occurred_at', [$from, $to])
            ->selectRaw('direction, COALESCE(SUM(amount), 0) as total')
            ->groupBy('direction')
            ->pluck('total', 'direction');

        return [
            'in'  => round((float) ($rows[1] ?? 0), 2),
            'out' => round((float) ($rows[-1] ?? 0), 2),
        ];
    }

    // ── Budget cycle ───────────────────────────────────────────────────────────

    /**
     * The cycle containing $at: from the payday on or before it up to the
     * moment before the next payday. A payday past the end of a short month
     * (31 in February) falls on that month's last day.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function cycleFor(Carbon $at): array
    {
        $day    = $this->cycle_day ?: 1;
        $payday = fn(Carbon $month) => $month->copy()->startOfMonth()
            ->day(min($day, $month->copy()->startOfMonth()->daysInMonth))->startOfDay();

        $start = $payday($at);
        if ($start->gt($at)) {
            $start = $payday($at->copy()->startOfMonth()->subMonthNoOverflow());
        }
        $end = $payday($start->copy()->startOfMonth()->addMonthNoOverflow())->subSecond();

        return [$start, $end];
    }

    /**
     * Where an envelope stands in the cycle containing $at.
     *
     * Money in during the cycle (a refund, a top-up) adds to what is
     * available; spending comes off it. `left` stops at zero and `over`
     * carries the overspend, so the account never shows a negative figure —
     * and the next cycle starts from the full amount regardless.
     */
    public function cycleSummary(?Carbon $at = null): array
    {
        [$start, $end] = $this->cycleFor($at ?? now());
        $moves     = $this->movementsBetween($start, $end);
        $available = round((float) $this->cycle_amount + $moves['in'], 2);
        $net       = round($available - $moves['out'], 2);

        return [
            'start'     => $start,
            'end'       => $end,
            'amount'    => round((float) $this->cycle_amount, 2),
            'available' => $available,
            'spent'     => $moves['out'],
            'left'      => max(0.0, $net),
            'over'      => max(0.0, -$net),
        ];
    }

    /**
     * The just-finished cycle, when it left money over that the user has
     * not decided about yet. Null when there is nothing to ask.
     *
     * A cycle the account did not fully exist for is skipped: an envelope
     * created mid-cycle would otherwise report its whole amount as saved.
     */
    public function pendingLeftover(): ?array
    {
        if (! $this->isBudget() || ! $this->is_active) {
            return null;
        }

        [$currentStart] = $this->cycleFor(now());
        $previous = $this->cycleSummary($currentStart->copy()->subSecond());

        if ($this->created_at && $this->created_at->gt($previous['start'])) {
            return null;
        }
        if ($this->cycle_settled_until && $this->cycle_settled_until->gte($previous['end'])) {
            return null;
        }

        return $previous['left'] > 0 ? $previous : null;
    }
}
