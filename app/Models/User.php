<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUlids, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'avatar_url',
        'currency_code', 'timezone', 'notifications_enabled',
        'family_id', 'family_role', 'locale', 'is_admin',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password'              => 'hashed',
            'notifications_enabled' => 'boolean',
            'is_admin' => 'boolean',
        ];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class, 'created_by');
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class, 'created_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'paid_by');
    }

    public function isAdmin(): bool
    {
        return (bool)$this->is_admin;
    }

    public function isFamilyOwner(): bool
    {
        return $this->family_role === 'owner';
    }

    public function isFamilyAdmin(): bool
    {
        return in_array($this->family_role, ['owner', 'admin']);
    }

    // Helper to get avatar url — prefer media library if available
    public function avatarUrl(): ?string
    {
        if (method_exists($this, 'hasMedia') && $this->hasMedia('avatars')) {
            if (method_exists($this, 'getFirstMediaUrl')) {
                // Try thumbnail first if available
                return $this->getFirstMediaUrl('avatars', 'thumb') ?: $this->getFirstMediaUrl('avatars') ?: $this->avatar_url;
            }
        }
        return $this->avatar_url;
    }

    // Provide a no-op conversion registrar if medialibrary is installed; guarded so no fatal when absent
    public function registerMediaConversions($media = null): void
    {
        if (!method_exists($this, 'addMediaConversion')) return;

        $this->addMediaConversion('thumb')
            ->fit('crop', 256, 256)
            ->performOnCollections('avatars');
    }
}
