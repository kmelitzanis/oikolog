<?php

namespace App\Models;

use App\Models\Concerns\SharedWithFamily;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * An everyday purchase: groceries, clothes, a haircut, a present.
 *
 * Not a bill: nothing recurs and nothing falls due. When it is paid from an
 * account, the account's withdrawal is written alongside it (see
 * App\Services\Ledger::spend()) and goes with it when it is deleted.
 */
class Expense extends Model
{
    use HasUlids, SharedWithFamily;

    /** Category => Material icon, in the order the picker lists them. */
    public const CATEGORIES = [
        'groceries'     => 'local_grocery_store',
        'dining'        => 'restaurant',
        'clothing'      => 'checkroom',
        'personal_care' => 'spa',
        'health'        => 'medication',
        'home'          => 'chair',
        'gifts'         => 'redeem',
        'kids'          => 'child_care',
        'entertainment' => 'theater_comedy',
        'transport'     => 'commute',
        'other'         => 'more_horiz',
    ];

    protected $fillable = [
        'name', 'category', 'amount', 'currency_code', 'spent_at', 'account_id',
        'notes', 'is_shared', 'family_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount'    => 'decimal:2',
            'spent_at'  => 'datetime',
            'is_shared' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // The withdrawal describes this purchase; without it, it would keep
        // the money out of the account for nothing.
        static::deleting(function (self $expense) {
            AccountTransaction::where('expense_id', $expense->getKey())->delete();
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(AccountTransaction::class);
    }

    public function icon(): string
    {
        return self::CATEGORIES[$this->category] ?? self::CATEGORIES['other'];
    }

    public function scopeInMonth($query, Carbon $month)
    {
        return $query->whereBetween('spent_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()]);
    }
}
