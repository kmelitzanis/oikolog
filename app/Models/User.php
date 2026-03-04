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
        'family_id', 'family_role',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password'              => 'hashed',
            'notifications_enabled' => 'boolean',
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'paid_by');
    }

    public function isFamilyOwner(): bool
    {
        return $this->family_role === 'owner';
    }

    public function isFamilyAdmin(): bool
    {
        return in_array($this->family_role, ['owner', 'admin']);
    }
}
