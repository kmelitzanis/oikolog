<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An amount parsed out of a provider's email, waiting to be accepted.
 *
 * Accepting writes it to the bill's `current_amount`; nothing else does.
 */
class BillAmountSuggestion extends Model
{
    use HasUlids;

    protected $fillable = [
        'bill_id', 'amount', 'status', 'message_uid', 'subject',
        'from_address', 'email_date', 'excerpt', 'resolved_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'      => 'decimal:2',
            'email_date'  => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
