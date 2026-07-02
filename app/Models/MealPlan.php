<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealPlan extends Model
{
    use HasFactory;

    public const MEAL_TYPES = ['breakfast', 'lunch', 'dinner', 'snack'];

    protected $fillable = [
        'user_id', 'date', 'meal_type', 'recipe_id', 'title', 'servings', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date'     => 'date',
            'servings' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /** Display name: linked recipe name or the custom title. */
    public function displayName(): string
    {
        return $this->recipe?->name ?? $this->title ?? '';
    }
}
