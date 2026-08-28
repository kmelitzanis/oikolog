<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provider extends Model
{
    use HasUlids;

    protected $fillable = [
        'name', 'website', 'phone', 'notes', 'logo_url',
        // How the invoice-mail crawler recognises this provider and finds the
        // total. See App\Services\BillAmountExtractor.
        'email_from_pattern', 'email_subject_pattern', 'email_amount_pattern',
    ];

    /** Whether the crawler has enough to go on for this provider. */
    public function canParseInvoiceMail(): bool
    {
        return filled($this->email_from_pattern) && filled($this->email_amount_pattern);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }
}
