<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's IMAP account, read only.
 *
 * The password is encrypted at rest by the cast. It still has to be decryptable
 * — IMAP needs the plaintext to log in — so an app-specific password scoped to
 * mail, not the account's real password, is the only sane thing to store here.
 */
class Mailbox extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id', 'host', 'port', 'encryption', 'username', 'password',
        'folder', 'is_active', 'last_scanned_at', 'last_error',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password'        => 'encrypted',
            'port'            => 'integer',
            'is_active'       => 'boolean',
            'last_scanned_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
