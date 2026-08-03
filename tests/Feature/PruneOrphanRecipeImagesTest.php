<?php

namespace Tests\Feature;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PruneOrphanRecipeImagesTest extends TestCase
{
    use RefreshDatabase;

    private function path(string $c): string
    {
        return 'recipes/' . str_repeat($c, 32) . '.jpg';
    }

    public function test_it_removes_only_old_unreferenced_images(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $referenced = $this->path('a');
        $orphanOld  = $this->path('b');
        $orphanNew  = $this->path('c');

        foreach ([$referenced, $orphanOld, $orphanNew] as $p) {
            Storage::disk('public')->put($p, 'x');
        }

        Recipe::create(['user_id' => $user->id, 'name' => 'Keeps its photo', 'servings' => 2, 'image_path' => $referenced]);

        // Age the two orphans differently: one abandoned, one still on screen.
        touch(Storage::disk('public')->path($orphanOld), now()->subDays(3)->getTimestamp());
        touch(Storage::disk('public')->path($orphanNew), now()->subMinutes(5)->getTimestamp());

        $this->artisan('recipes:prune-images')->assertSuccessful();

        Storage::disk('public')->assertExists($referenced);
        Storage::disk('public')->assertMissing($orphanOld);
        // A photo uploaded minutes ago is unreferenced by definition — the user
        // is still filling in the form.
        Storage::disk('public')->assertExists($orphanNew);
    }

    public function test_dry_run_deletes_nothing(): void
    {
        Storage::fake('public');
        $orphan = $this->path('d');
        Storage::disk('public')->put($orphan, 'x');
        touch(Storage::disk('public')->path($orphan), now()->subDays(3)->getTimestamp());

        $this->artisan('recipes:prune-images --dry-run')->assertSuccessful();

        Storage::disk('public')->assertExists($orphan);
    }
}
