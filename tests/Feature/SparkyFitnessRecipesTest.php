<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Recipe;
use App\Models\User;
use App\Services\SafeUrlFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Recipes for SparkyFitness: the Mealie-shaped API it reads them through, the
 * key it reads them with, and the nutrition per serving it logs.
 */
class SparkyFitnessRecipesTest extends TestCase
{
    use RefreshDatabase;

    private function recipe(User $user, array $attrs = []): Recipe
    {
        return Recipe::create(array_merge([
            'user_id'   => $user->id,
            'name'      => 'Φακές σούπα',
            'servings'  => 4,
            'nutrition' => ['calories' => 320, 'protein' => 18.5, 'sodium' => 540],
        ], $attrs));
    }

    /** A key made the way Settings makes it. */
    private function token(User $user): string
    {
        $this->actingAs($user)->post(route('settings.sparky.store'))->assertRedirect(route('settings'));
        $token = session('sparky_token');
        $this->app['auth']->forgetGuards();

        return $token;
    }

    // ── Mealie-compatible API ───────────────────────────────────────────

    public function test_search_answers_the_way_sparkyfitness_asks(): void
    {
        $user = User::factory()->create();
        $soup = $this->recipe($user);
        $this->recipe($user, ['name' => 'Μουσακάς']);
        $this->recipe(User::factory()->create(), ['name' => 'Φακές της γειτόνισσας']);
        $token = $this->token($user);

        $response = $this->withToken($token)
            ->getJson('/api/recipes?' . http_build_query(['queryFilter' => 'name LIKE "%Φακές%"', 'perPage' => 10, 'page' => 1]))
            ->assertOk();

        $response->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.slug', $soup->uuid)
            ->assertJsonPath('items.0.name', 'Φακές σούπα')
            ->assertJsonPath('items.0.recipeServings', 4);
    }

    public function test_a_recipe_carries_its_nutrition_per_serving(): void
    {
        $user = User::factory()->create();
        $soup = $this->recipe($user);
        $token = $this->token($user);

        $this->withToken($token)->getJson("/api/recipes/{$soup->uuid}")
            ->assertOk()
            ->assertJsonPath('name', 'Φακές σούπα')
            ->assertJsonPath('nutrition.calories', '320')
            ->assertJsonPath('nutrition.proteinContent', '18.5')
            ->assertJsonPath('nutrition.sodiumContent', '540')
            ->assertJsonPath('nutrition.fatContent', null);
    }

    public function test_another_users_recipe_is_not_found(): void
    {
        $user = User::factory()->create();
        $theirs = $this->recipe(User::factory()->create());
        $token = $this->token($user);

        $this->withToken($token)->getJson("/api/recipes/{$theirs->uuid}")->assertNotFound();
    }

    public function test_no_key_no_recipes(): void
    {
        $this->getJson('/api/recipes')->assertUnauthorized();
    }

    public function test_a_key_without_the_recipe_ability_is_refused(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('other', ['bills:read'])->plainTextToken;

        $this->withToken($token)->getJson('/api/recipes')->assertForbidden();
    }

    public function test_the_photo_is_served_at_mealies_media_path(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('recipes/soup.jpg', UploadedFile::fake()->image('soup.jpg')->getContent());
        $soup = $this->recipe(User::factory()->create(), ['image_path' => 'recipes/soup.jpg']);

        $this->get("/api/media/recipes/{$soup->uuid}/images/min-original.webp")->assertOk();
        $this->get("/api/media/recipes/{$soup->uuid}/images/../../.env")->assertNotFound();
    }

    // ── The key ─────────────────────────────────────────────────────────

    public function test_a_new_key_replaces_the_old_one(): void
    {
        $user = User::factory()->create();
        $this->recipe($user);
        $first = $this->token($user);
        $second = $this->token($user);

        $this->withToken($second)->getJson('/api/recipes')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($first)->getJson('/api/recipes')->assertUnauthorized();
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_revoking_the_key_stops_it_working(): void
    {
        $user = User::factory()->create();
        $token = $this->token($user);

        $this->actingAs($user)->delete(route('settings.sparky.destroy'))->assertRedirect(route('settings'));
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/recipes')->assertUnauthorized();
    }

    public function test_settings_shows_the_key_once(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('settings.sparky.store'));
        $token = session('sparky_token');

        // On the page the redirect lands on, and never again.
        $this->actingAs($user)->get(route('settings'))->assertOk()->assertSee($token);
        $this->actingAs($user)->get(route('settings'))->assertOk()->assertDontSee($token);
    }

    // ── Nutrition ───────────────────────────────────────────────────────

    public function test_nutrition_is_worked_out_from_weighed_ingredients(): void
    {
        Product::create(['name' => 'Φακές', 'nutrition' => ['calories' => 350, 'protein' => 24, 'salt' => 0.05]]);
        Product::create(['name' => 'Ελαιόλαδο', 'nutrition' => ['calories' => 900, 'fat' => 100]]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('recipes.nutrition.estimate'), [
                'servings'    => 4,
                'ingredients' => [
                    ['name' => 'φακές', 'quantity' => 500, 'unit' => 'g'],
                    ['name' => 'Ελαιόλαδο', 'quantity' => 0.1, 'unit' => 'l'],
                    // A spoonful has no weight to go on.
                    ['name' => 'Ελαιόλαδο', 'quantity' => 2, 'unit' => 'tbsp'],
                    ['name' => 'Δάφνη', 'quantity' => 2, 'unit' => 'piece'],
                ],
            ])
            ->assertOk()
            // (1750 + 900) / 4 kcal; 120 g protein / 4; 100 g fat / 4;
            // 0.25 g salt is 100 mg sodium, / 4.
            ->assertJsonPath('nutrition.calories', 662.5)
            ->assertJsonPath('nutrition.protein', 30)
            ->assertJsonPath('nutrition.fat', 25)
            ->assertJsonPath('nutrition.sodium', 25)
            ->assertJsonPath('counted', ['φακές', 'Ελαιόλαδο'])
            ->assertJsonPath('skipped', ['Ελαιόλαδο', 'Δάφνη']);
    }

    public function test_saving_keeps_only_the_figures_filled_in(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('recipes.store'), [
            'name'        => 'Χωριάτικη',
            'ingredients' => [['name' => 'ντομάτα', 'quantity' => 2, 'unit' => 'piece']],
            'nutrition'   => ['calories' => '210', 'protein' => '', 'fat' => '16.04'],
        ])->assertCreated();

        $this->assertEquals(['calories' => 210, 'fat' => 16], Recipe::firstWhere('name', 'Χωριάτικη')->nutrition);
    }

    public function test_an_untouched_nutrition_block_stores_nothing(): void
    {
        $this->actingAs(User::factory()->create())->postJson(route('recipes.store'), [
            'name'        => 'Σαλάτα',
            'ingredients' => [['name' => 'μαρούλι', 'quantity' => 1, 'unit' => 'piece']],
            'nutrition'   => ['calories' => null, 'protein' => ''],
        ])->assertCreated();

        $this->assertNull(Recipe::firstWhere('name', 'Σαλάτα')->nutrition);
    }

    public function test_the_import_reads_the_pages_nutrition(): void
    {
        $this->app->bind(SafeUrlFetcher::class, fn () => new class extends SafeUrlFetcher {
            protected function resolve(string $host): array
            {
                return ['93.184.216.34'];
            }
        });

        $json = json_encode([
            '@context'         => 'https://schema.org',
            '@type'            => 'Recipe',
            'name'             => 'Γεμιστά',
            'recipeIngredient' => ['6 ντομάτες'],
            'nutrition'        => [
                '@type'               => 'NutritionInformation',
                'calories'            => '1255 kJ',
                'proteinContent'      => '7,5 g',
                'fatContent'          => '14 g',
                'sodiumContent'       => '0.6 g',
                'saturatedFatContent' => '2200 mg',
            ],
        ]);

        Http::fake(['https://recipes.example/*' => Http::response(
            "<html><head><script type=\"application/ld+json\">{$json}</script></head></html>",
            200,
            ['Content-Type' => 'text/html'],
        )]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('recipes.import'), ['url' => 'https://recipes.example/gemista'])
            ->assertOk()
            ->assertJson(['data' => ['nutrition' => [
                'calories'      => 300,
                'protein'       => 7.5,
                'fat'           => 14,
                'saturated_fat' => 2.2,
                'sodium'        => 600,
            ]]]);
    }
}
