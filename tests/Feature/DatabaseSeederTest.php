<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Provider;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_default_providers_linked_to_their_categories(): void
    {
        // Providers now belong to categories through the pivot only; a leftover
        // required providers.category_id made seeding fail on SQLite.
        $this->assertFalse(Schema::hasColumn('providers', 'category_id'));

        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThan(0, Category::where('is_system', true)->count());
        $this->assertGreaterThan(0, Provider::count());
        $this->assertGreaterThan(0, DB::table('category_provider')->count());

        $bank = Provider::where('name', 'Alpha Bank')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            ['Loan', 'Credit Card'],
            $bank->categories()->pluck('name')->all(),
        );

        // Every seeded provider has at least one category.
        $this->assertSame(0, Provider::doesntHave('categories')->count());
    }

    public function test_seeding_twice_does_not_duplicate_providers_or_links(): void
    {
        $this->seed(DatabaseSeeder::class);
        $providers = Provider::count();
        $links = DB::table('category_provider')->count();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($providers, Provider::count());
        $this->assertSame($links, DB::table('category_provider')->count());
    }
}
