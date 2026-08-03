<?php

namespace App\Console\Commands;

use App\Models\Recipe;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes recipe photos no recipe points at.
 *
 * Photos are stored the moment they're chosen so the form can preview them,
 * which means abandoning a form — or importing a recipe and never saving it —
 * leaves the file behind. Nothing else ever reclaims those, so without this the
 * directory only grows.
 *
 * Only files older than the grace period are touched: a freshly uploaded image
 * is unreferenced by definition while the user is still filling in the form.
 */
class PruneOrphanRecipeImages extends Command
{
    protected $signature = 'recipes:prune-images {--hours=24 : Minimum age before a file is considered abandoned}
                                                 {--dry-run : List what would be deleted without deleting it}';

    protected $description = 'Delete recipe images that no recipe references';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $cutoff = Carbon::now()->subHours((int) $this->option('hours'));
        $dryRun = (bool) $this->option('dry-run');

        $referenced = Recipe::whereNotNull('image_path')->pluck('image_path')->flip();

        $deleted = 0;
        $freed = 0;

        foreach ($disk->files('recipes') as $path) {
            if ($referenced->has($path)) {
                continue;
            }

            if (Carbon::createFromTimestamp($disk->lastModified($path))->gt($cutoff)) {
                continue; // still in a form somewhere
            }

            $freed += $disk->size($path);
            $deleted++;

            if ($dryRun) {
                $this->line("would delete: {$path}");
            } else {
                $disk->delete($path);
            }
        }

        $this->info(sprintf(
            '%s %d orphaned image(s), %s KB.',
            $dryRun ? 'Would remove' : 'Removed',
            $deleted,
            number_format($freed / 1024, 1)
        ));

        return self::SUCCESS;
    }
}
