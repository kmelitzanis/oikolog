<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Recipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'description', 'servings',
        'image_path', 'source_url',
        // `instructions` is deliberately absent: the method lives in recipe_steps
        // now. The column is kept, unwritten, as a rollback copy of the old data.
        'prep_minutes', 'cook_minutes', 'difficulty', 'is_favorite',
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

    /** Public URL for the recipe photo, or null when it has none. */
    public function imageUrl(): ?string
    {
        return $this->image_path
            ? Storage::disk('public')->url($this->image_path)
            : null;
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(RecipeStep::class)->orderBy('sort_order');
    }

    public function mealPlans(): HasMany
    {
        return $this->hasMany(MealPlan::class);
    }

    /**
     * Ingredients grouped under their section heading, in stored order.
     *
     * Returns a single group keyed by '' when the recipe isn't split into parts,
     * so callers can render one code path either way.
     *
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection>
     */
    public function ingredientsBySection()
    {
        return $this->ingredients
            ->sortBy('sort_order')
            ->groupBy(fn ($i) => $i->section ?? '');
    }

    /**
     * Method steps grouped under their section heading, in stored order.
     *
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection>
     */
    public function stepsBySection()
    {
        return $this->steps
            ->sortBy('sort_order')
            ->groupBy(fn ($s) => $s->section ?? '');
    }

    /** True when the recipe is written in named parts rather than one run. */
    public function hasSections(): bool
    {
        return $this->steps->contains(fn ($s) => filled($s->section))
            || $this->ingredients->contains(fn ($i) => filled($i->section));
    }

    public function totalMinutes(): ?int
    {
        if ($this->prep_minutes === null && $this->cook_minutes === null) return null;
        return (int) $this->prep_minutes + (int) $this->cook_minutes;
    }


}
