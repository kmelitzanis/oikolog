<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One movement on one account. `amount` is always positive and `direction`
 * carries the sign (+1 in, -1 out), so a row reads on its own and summing the
 * ledger is a single expression.
 *
 * A transfer is two rows sharing a `transfer_group`: one out, one in.
 */
class AccountTransaction extends Model
{
    use HasUlids;

    public const TYPES = ['deposit', 'withdrawal', 'transfer_in', 'transfer_out', 'adjustment'];

    protected $fillable = [
        'account_id', 'type', 'amount', 'direction', 'currency_code',
        'occurred_at', 'description', 'income_id', 'payment_id',
        'transfer_group', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'direction' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function income(): BelongsTo
    {
        return $this->belongsTo(Income::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The other side of a transfer, if this row is one half of one. */
    public function counterpart(): ?self
    {
        if (!$this->transfer_group) return null;

        return static::where('transfer_group', $this->transfer_group)
            ->where('id', '!=', $this->id)
            ->first();
    }

    public function isIncoming(): bool
    {
        return $this->direction > 0;
    }

    public function signedAmount(): float
    {
        return round((float) $this->amount * $this->direction, 2);
    }
}
