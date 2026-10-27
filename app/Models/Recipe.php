<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Recipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'description', 'servings',
        'image_path', 'source_url',
        // `instructions` is deliberately absent: the method lives in recipe_steps
        // now. The column is kept, unwritten, as a rollback copy of the old data.
        'prep_minutes', 'cook_minutes', 'difficulty', 'is_favorite', 'nutrition',
    ];

    /**
     * Nutrition is per serving: calories in kcal, sodium in mg, the rest in
     * grams. The same keys as a product's per-100 g figures, so the two can
     * be added up without a translation table.
     */
    public const NUTRIENTS = ['calories', 'protein', 'carbs', 'sugar', 'fat', 'saturated_fat', 'fiber', 'sodium'];

    protected function casts(): array
    {
        return [
            'servings'     => 'integer',
            'prep_minutes' => 'integer',
            'cook_minutes' => 'integer',
            'is_favorite'  => 'boolean',
            'nutrition'    => 'array',
        ];
    }

    protected static function booted(): void
    {
        // The id outside apps are given; see the migration that added it.
        static::creating(function (self $recipe) {
            $recipe->uuid ??= (string) Str::uuid();
        });
    }

    /** The filled-in nutrition values per serving, in display order. */
    public function nutritionFacts(): array
    {
        $values = $this->nutrition ?? [];
        $facts = [];

        foreach (self::NUTRIENTS as $key) {
            if (isset($values[$key]) && $values[$key] !== '') {
                $facts[$key] = (float) $values[$key];
            }
        }

        return $facts;
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
