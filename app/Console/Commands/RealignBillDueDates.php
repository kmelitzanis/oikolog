<?php

namespace App\Console\Commands;

use App\Models\Bill;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RealignBillDueDates extends Command
{
    protected $signature   = 'bills:realign {--dry-run : List affected bills without changing them}';
    protected $description = 'Snap bills whose next_due_date has drifted off their recurrence schedule back onto it';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $fixed = 0;

        Bill::whereNotNull('start_date')
            ->whereNotNull('next_due_date')
            ->where('frequency', '!=', 'once')
            ->chunkById(200, function ($bills) use ($dry, &$fixed) {
                foreach ($bills as $bill) {
                    if ($this->onSchedule($bill)) continue;

                    $old = Carbon::parse($bill->next_due_date)->toDateString();
                    $bill->realignNextDueDate();
                    $new = Carbon::parse($bill->next_due_date)->toDateString();

                    if ($old === $new) continue;

                    $this->line(sprintf('%s  %-30s  %s → %s', $bill->id, mb_strimwidth($bill->name, 0, 30), $old, $new));
                    if (! $dry) $bill->save();
                    $fixed++;
                }
            });

        $this->info($dry
            ? "{$fixed} bill(s) would be realigned. Re-run without --dry-run to apply."
            : "{$fixed} bill(s) realigned.");

        return self::SUCCESS;
    }

    /** True when next_due_date already falls on an occurrence of the schedule anchored at start_date. */
    private function onSchedule(Bill $bill): bool
    {
        $start = Carbon::parse($bill->start_date)->startOfDay();
        $due   = Carbon::parse($bill->next_due_date)->startOfDay();

        if ($due->lt($start)) return false;

        $occurrences = $bill->occurrencesBetween($start->copy(), $due->copy());
        $last = end($occurrences) ?: null;

        return $last instanceof Carbon && $last->toDateString() === $due->toDateString();
    }
}
