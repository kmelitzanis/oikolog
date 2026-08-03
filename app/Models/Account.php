<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A place the user keeps money — they name and create their own; the app
 * ships none. Balance is never stored: it is the opening balance plus the
 * signed sum of the ledger, so it can't drift away from the history.
 */
class Account extends Model
{
    use HasUlids;

    protected $fillable = [
        'name', 'description', 'icon', 'color_hex', 'opening_balance',
        'currency_code', 'is_active', 'is_shared', 'notes', 'created_by', 'family_id',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
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

    // ── Balance ────────────────────────────────────────────────────────────────

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
}
