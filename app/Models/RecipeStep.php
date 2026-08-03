<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step of a recipe's method.
 *
 * `section` is the heading it sits under ("Για τη βάση") or null when the recipe
 * isn't split into parts. Steps are numbered per section in the UI, so ordering
 * is carried explicitly rather than inferred from insertion order.
 */
class RecipeStep extends Model
{
    use HasFactory;

    protected $fillable = ['recipe_id', 'section', 'sort_order', 'text'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}
