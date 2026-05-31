<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'brand', 'barcode', 'category',
        'unit', 'default_quantity', 'image_path', 'image_url',
        'nutrition', 'nutri_score', 'eco_score', 'user_id',
    ];

    protected $casts = [
        'nutrition' => 'array',
        'default_quantity' => 'float',
    ];

    /**
     * Scope: return global (admin) products plus products owned by this user.
     */
    public function scopeForUser($query, $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->whereNull('user_id')->orWhere('user_id', $user->id);
        });
    }

    /**
     * Scope: simple full-text search across name, brand, barcode.
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('brand', 'like', "%{$term}%")
                ->orWhere('barcode', 'like', "%{$term}%");
        });
    }
}


