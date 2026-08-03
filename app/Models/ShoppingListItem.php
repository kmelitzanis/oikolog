<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShoppingListItem extends Model
{
    use HasUlids, HasFactory;

    protected $fillable = [
        'shopping_list_id',
        'product_id',
        'name',
        'quantity',
        'unit',
        'barcode',
        'nutrition',
        'checked',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'nutrition' => 'array',
            'checked'   => 'boolean',
            'checked_at' => 'datetime',
            'quantity'  => 'decimal:2',
        ];
    }

    public function shoppingList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class);
    }

    /**
     * The catalogue entry behind this line, when there is one. Free-text items
     * have none and fall back to their own `name` and `nutrition`.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * How long a tick stays part of the current shopping round.
     *
     * Lists are kept permanently stocked and mostly ticked; a tick older than
     * this belongs to a previous trip and no longer counts as progress.
     */
    public const ROUND_HOURS = 24;

    /** Was this ticked off during the round that is still running? */
    public function checkedThisRound(): bool
    {
        return $this->checked
            && $this->checked_at
            && $this->checked_at->gt(now()->subHours(self::ROUND_HOURS));
    }

    /** Nutri-Score to show beside the line, from the product if linked. */
    public function grade(): ?string
    {
        return $this->product?->grade();
    }
}
