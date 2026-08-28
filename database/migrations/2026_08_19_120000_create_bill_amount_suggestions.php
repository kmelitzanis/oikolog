<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One IMAP account per user, read only. The crawler never sends, never
        // deletes, and never marks anything read.
        Schema::create('mailboxes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(993);
            $table->string('encryption', 10)->default('ssl');   // ssl | tls | none
            $table->string('username');
            $table->text('password');                            // encrypted cast
            $table->string('folder')->default('INBOX');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_scanned_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });

        Schema::table('providers', function (Blueprint $table) {
            // How to recognise this provider's invoice mail, and where the
            // total sits in it. All optional: a provider with no patterns is
            // simply skipped by the crawler.
            $table->string('email_from_pattern')->nullable();   // regex on From
            $table->string('email_subject_pattern')->nullable();// regex on Subject
            $table->text('email_amount_pattern')->nullable();   // regex, group 1 = amount
        });

        // A parsed figure waiting for a human to accept it. Nothing touches a
        // bill's amount until someone confirms — a bad regex writing 1080 for
        // 108 silently would be worse than having no crawler at all.
        Schema::create('bill_amount_suggestions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('bill_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('status', 12)->default('pending');   // pending | accepted | rejected
            $table->string('message_uid')->nullable();
            $table->string('subject')->nullable();
            $table->string('from_address')->nullable();
            $table->timestamp('email_date')->nullable();
            $table->text('excerpt')->nullable();                // the matched fragment, for review
            $table->foreignUlid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // The same email must never queue twice.
            $table->unique(['bill_id', 'message_uid']);
            $table->index(['bill_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_amount_suggestions');
        Schema::dropIfExists('mailboxes');

        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn(['email_from_pattern', 'email_subject_pattern', 'email_amount_pattern']);
        });
    }
};
