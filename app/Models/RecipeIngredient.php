<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeIngredient extends Model
{
    use HasFactory;

    protected $fillable = ['recipe_id', 'section', 'sort_order', 'product_id', 'name', 'quantity', 'unit'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    /** The unit rendered in the viewer's language — never show the raw key. */
    public function unitLabel(): string
    {
        return \App\Support\Units::label($this->unit);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

