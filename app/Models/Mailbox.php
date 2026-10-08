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
 *
 * A `microsoft` mailbox has no password at all: it was connected by signing in
 * with Microsoft, and holds an OAuth refresh token instead (also encrypted).
 */
class Mailbox extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id', 'host', 'port', 'encryption', 'auth_type', 'username', 'password',
        'oauth_refresh_token', 'oauth_access_token', 'oauth_expires_at',
        'folder', 'is_active', 'last_scanned_at', 'last_error',
    ];

    protected $hidden = ['password', 'oauth_refresh_token', 'oauth_access_token'];

    protected function casts(): array
    {
        return [
            'password'            => 'encrypted',
            'oauth_refresh_token' => 'encrypted',
            'oauth_access_token'  => 'encrypted',
            'oauth_expires_at'    => 'datetime',
            'port'            => 'integer',
            'is_active'       => 'boolean',
            'last_scanned_at' => 'datetime',
        ];
    }

    public function usesMicrosoft(): bool
    {
        return $this->auth_type === 'microsoft';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
