<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'description', 'servings',
        'prep_minutes', 'cook_minutes', 'difficulty', 'instructions', 'emoji', 'is_favorite',
    ];

    protected function casts(): array
    {
        return [
            'servings'     => 'integer',
            'prep_minutes' => 'integer',
            'cook_minutes' => 'integer',
            'is_favorite'  => 'boolean',
        ];
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    public function mealPlans(): HasMany
    {
        return $this->hasMany(MealPlan::class);
    }

    public function totalMinutes(): ?int
    {
        if ($this->prep_minutes === null && $this->cook_minutes === null) return null;
        return (int) $this->prep_minutes + (int) $this->cook_minutes;
    }

    /** Instructions split into trimmed, non-empty steps (one per line). */
    public function steps(): array
    {
        if (! $this->instructions) return [];
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $this->instructions))));
    }
}
