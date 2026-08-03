<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When an item was ticked off, not just whether it is ticked.
 *
 * Lists here are kept the way Reminders is used: everything sits ticked, and
 * only what is needed this time gets un-ticked. Without a timestamp the
 * progress bar reads 100% forever, because it counts ticks from months ago.
 * With one, progress can be about the current shopping round and the ticks
 * themselves can stay exactly where they are.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->timestamp('checked_at')->nullable()->after('checked');
        });

        // Existing ticks are historic: date them to their last change so they
        // count as an old round rather than as a burst of activity today.
        DB::table('shopping_list_items')
            ->where('checked', true)
            ->update(['checked_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->dropColumn('checked_at');
        });
    }
};
