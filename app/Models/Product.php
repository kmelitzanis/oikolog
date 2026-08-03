<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One entry in the shared product catalogue.
 *
 * The catalogue is common to everyone and builds itself: scanning a barcode on
 * a shopping list creates the product, and from then on every list that item
 * appears on points at the same record. `user_id` records who first added it,
 * nothing more — it does not limit who can see it.
 */
class Product extends Model
{
    use HasFactory;

    public const SOURCE_API = 'open_food_facts';
    public const SOURCE_MANUAL = 'manual';

    /** Nutri-Score grades, best first. */
    public const GRADES = ['a', 'b', 'c', 'd', 'e'];

    protected $fillable = [
        'name', 'description', 'ingredients_text', 'allergens', 'brand', 'barcode',
        'category', 'unit', 'default_quantity', 'net_quantity', 'serving_size',
        'image_path', 'image_url', 'nutrition', 'nutri_score', 'eco_score',
        'nova_group', 'source', 'last_synced_at', 'is_edited', 'user_id',
    ];

    protected $casts = [
        'nutrition' => 'array',
        'allergens' => 'array',
        'default_quantity' => 'float',
        'nova_group' => 'integer',
        'is_edited' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    // ── Relations ──────────────────────────────────────────────────────────────
    public function purchases(): HasMany
    {
        return $this->hasMany(ProductPurchase::class);
    }

    public function listItems(): HasMany
    {
        return $this->hasMany(ShoppingListItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    /**
     * Kept for callers that still scope by user. The catalogue is shared, so
     * this deliberately filters nothing.
     */
    public function scopeForUser($query, $user)
    {
        return $query;
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('brand', 'like', "%{$term}%")
                ->orWhere('barcode', 'like', "%{$term}%");
        });
    }

    public function scopeGrade($query, string $grade)
    {
        return $query->where('nutri_score', strtolower($grade));
    }

    // ── Presentation ───────────────────────────────────────────────────────────

    /** A locally stored image wins over the remote one — it outlives the source. */
    public function imageUrl(): ?string
    {
        if ($this->image_path) {
            return asset('storage/' . ltrim($this->image_path, '/'));
        }

        return $this->image_url ?: null;
    }

    public function grade(): ?string
    {
        $grade = strtolower((string) $this->nutri_score);

        return in_array($grade, self::GRADES, true) ? $grade : null;
    }

    /** Whether a refresh from the barcode API is possible at all. */
    public function isRefreshable(): bool
    {
        return (bool) $this->barcode;
    }

    /** The nutrition values that are actually filled in, in display order. */
    public function nutritionFacts(): array
    {
        $order = ['calories', 'protein', 'carbs', 'sugar', 'fat', 'saturated_fat', 'fiber', 'salt', 'sodium'];
        $values = $this->nutrition ?? [];
        $facts = [];

        foreach ($order as $key) {
            $value = $values[$key] ?? null;
            if ($value === null || $value === '') continue;
            $facts[$key] = (float) $value;
        }

        return $facts;
    }

    public function hasNutrition(): bool
    {
        return $this->nutritionFacts() !== [];
    }

    // ── Buying rhythm ──────────────────────────────────────────────────────────

    /**
     * The average number of days between purchases, or null when there is not
     * enough history to say anything honest (fewer than three buys).
     *
     * Two purchases can be a coincidence; three start to be a habit, and this
     * figure is only worth showing once it is one.
     */
    public function averageDaysBetweenPurchases(): ?int
    {
        $dates = $this->purchases()->orderBy('purchased_at')->pluck('purchased_at');

        if ($dates->count() < 3) {
            return null;
        }

        $first = $dates->first();
        $last = $dates->last();
        $span = $first->diffInDays($last);

        return $span > 0 ? max(1, (int) round($span / ($dates->count() - 1))) : null;
    }

    /** When the rhythm suggests it will run out again. */
    public function expectedNextPurchase(): ?\Carbon\Carbon
    {
        $every = $this->averageDaysBetweenPurchases();
        $last = $this->purchases()->max('purchased_at');

        return $every && $last ? \Carbon\Carbon::parse($last)->addDays($every) : null;
    }
}
