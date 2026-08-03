<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * Scanning a barcode to check something in the shop leaves a product behind
 * even when it never reaches a list. This clears those away again.
 *
 * Deliberately narrow: only rows that came from the API, were never bought,
 * are on no list, and nobody has edited. Anything a person touched or bought
 * is kept, because the purchase history refers to it.
 */
class PruneUnusedProducts extends Command
{
    protected $signature = 'products:prune {--days=30 : Only prune products older than this} {--dry-run}';

    protected $description = 'Remove API-sourced products that were never bought, listed or edited';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $stale = Product::query()
            ->where('source', Product::SOURCE_API)
            ->where('is_edited', false)
            ->where('created_at', '<', now()->subDays($days))
            ->whereDoesntHave('purchases')
            ->whereDoesntHave('listItems')
            ->get();

        if ($stale->isEmpty()) {
            $this->info('Nothing to prune.');

            return self::SUCCESS;
        }

        foreach ($stale as $product) {
            $this->line(($this->option('dry-run') ? '[dry-run] ' : '') . "Pruning #{$product->id} {$product->name}");

            if (! $this->option('dry-run')) {
                $product->delete();
            }
        }

        $this->info($stale->count() . ' product(s) pruned.');

        return self::SUCCESS;
    }
}
