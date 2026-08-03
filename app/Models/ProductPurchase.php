<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product actually bought. Written when a shopping list item is ticked off
 * and removed if it is un-ticked, so no extra step is needed to build history.
 *
 * The list references are nullable on purpose: a purchase has to outlive the
 * list it came from.
 */
class ProductPurchase extends Model
{
    use HasUlids;

    protected $fillable = [
        'product_id', 'shopping_list_id', 'shopping_list_item_id',
        'quantity', 'unit', 'price', 'purchased_at', 'purchased_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'price' => 'decimal:2',
            'purchased_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function shoppingList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchased_by');
    }
}
