<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outlook.com and Microsoft 365 no longer accept any password over IMAP, so a
 * mailbox can now be connected with OAuth instead: the user signs in with
 * Microsoft once and the app keeps a refresh token, never a password.
 *
 * Tokens are stored encrypted (see the model casts). The password column
 * becomes optional — an OAuth mailbox has none.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('mailboxes', function (Blueprint $table) {
            $table->string('auth_type', 20)->default('password')->after('encryption'); // password | microsoft
            $table->text('oauth_refresh_token')->nullable()->after('password');
            $table->text('oauth_access_token')->nullable()->after('oauth_refresh_token');
            $table->timestamp('oauth_expires_at')->nullable()->after('oauth_access_token');
        });

        Schema::table('mailboxes', function (Blueprint $table) {
            $table->text('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('mailboxes', function (Blueprint $table) {
            $table->dropColumn(['auth_type', 'oauth_refresh_token', 'oauth_access_token', 'oauth_expires_at']);
        });
    }
};
