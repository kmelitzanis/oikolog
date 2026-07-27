<?php

use App\Models\Bill;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── Default income per bill (pre-selected when paying) ──────────────
        if (! Schema::hasColumn('bills', 'default_income_id')) {
            Schema::table('bills', function (Blueprint $table) {
                $table->char('default_income_id', 26)->nullable()->after('assigned_to');
            });
        }

        // ── Heal drifted next_due_date on recurring bills ───────────────────
        // Bills that were switched between frequencies (e.g. monthly → yearly)
        // kept a next_due_date on the old cadence, so quarterly/yearly bills
        // looked overdue every month. Snap any off-schedule due date back onto
        // the schedule anchored at start_date. Wrapped so a data hiccup never
        // fails the deploy.
        try {
            Bill::query()
                ->whereNotNull('start_date')
                ->whereNotNull('next_due_date')
                ->where('frequency', '!=', 'once')
                ->chunkById(200, function ($bills) {
                    foreach ($bills as $bill) {
                        if ($this->onSchedule($bill)) continue;
                        $bill->realignNextDueDate();
                        $bill->saveQuietly();
                    }
                });
        } catch (\Throwable $e) {
            // Non-fatal: healing is best-effort.
            logger()->warning('Bill due-date healing skipped: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bills', 'default_income_id')) {
            Schema::table('bills', function (Blueprint $table) {
                $table->dropColumn('default_income_id');
            });
        }
    }

    /** True when next_due_date already lands on an occurrence anchored at start_date. */
    private function onSchedule(Bill $bill): bool
    {
        $start = Carbon::parse($bill->start_date)->startOfDay();
        $due   = Carbon::parse($bill->next_due_date)->startOfDay();
        if ($due->lt($start)) return false;

        $occ = $bill->occurrencesBetween($start->copy(), $due->copy());
        $last = end($occ) ?: null;

        return $last instanceof Carbon && $last->toDateString() === $due->toDateString();
    }
};
