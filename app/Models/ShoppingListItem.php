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
}
