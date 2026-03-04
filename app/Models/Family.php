<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Family extends Model
{
    use HasUlids;

    protected $fillable = ['name', 'owner_id', 'invite_code'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($f) {
            $f->invite_code ??= strtoupper(Str::random(8));
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function regenerateInviteCode(): string
    {
        $this->update(['invite_code' => strtoupper(Str::random(8))]);
        return $this->invite_code;
    }
}
