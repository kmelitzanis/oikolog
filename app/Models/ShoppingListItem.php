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
    ];

    protected function casts(): array
    {
        return [
            'nutrition' => 'array',
            'checked'   => 'boolean',
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

    /** Nutri-Score to show beside the line, from the product if linked. */
    public function grade(): ?string
    {
        return $this->product?->grade();
    }
}
