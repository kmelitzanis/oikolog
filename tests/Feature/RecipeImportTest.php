<?php

namespace Tests\Feature;

use App\Models\Recipe;
use App\Models\User;
use App\Services\SafeUrlFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class RecipeImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Swap in a resolver so the fetch tests don't depend on live DNS.
     *
     * The guard itself is unchanged and still decides which addresses are
     * allowed — only the hostname→IP lookup is stubbed. Test domains map to a
     * public address; literal IPs pass straight through, so a redirect to
     * 169.254.169.254 is still judged on its real merits.
     */
    private function fakeDns(): void
    {
        $this->app->bind(SafeUrlFetcher::class, fn () => new class extends SafeUrlFetcher {
            protected function resolve(string $host): array
            {
                if (filter_var($host, FILTER_VALIDATE_IP)) {
                    return [$host];
                }

                return ['93.184.216.34']; // a public address
            }
        });
    }

    private function page(array $recipe): string
    {
        $json = json_encode(array_merge(['@context' => 'https://schema.org', '@type' => 'Recipe'], $recipe));

        return <<<HTML
            <html><head>
              <script type="application/ld+json">{$json}</script>
            </head><body></body></html>
            HTML;
    }

    // ── SSRF guard ───────────────────────────────────────────────────────
    // Import makes the server fetch a URL the user typed. Without these checks
    // the app is a proxy into its own network.

    public static function blockedUrls(): array
    {
        return [
            'loopback'        => ['http://127.0.0.1/recipe'],
            'loopback name'   => ['http://localhost/recipe'],
            'private 10/8'    => ['http://10.0.0.5/recipe'],
            'private 192.168' => ['http://192.168.1.10/recipe'],
            'private 172.16'  => ['http://172.16.0.1/recipe'],
            'cloud metadata'  => ['http://169.254.169.254/latest/meta-data/'],
            'ipv6 loopback'   => ['http://[::1]/recipe'],
        ];
    }

    /** @dataProvider blockedUrls */
    public function test_private_and_reserved_addresses_are_refused(string $url): void
    {
        $this->expectException(RuntimeException::class);

        app(SafeUrlFetcher::class)->assertSafe($url);
    }

    public function test_non_http_schemes_are_refused(): void
    {
        foreach (['file:///etc/passwd', 'gopher://example.com', 'ftp://example.com'] as $url) {
            try {
                app(SafeUrlFetcher::class)->assertSafe($url);
                $this->fail("Expected {$url} to be refused");
            } catch (RuntimeException $e) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_a_redirect_into_a_private_address_is_refused(): void
    {
        // A public host is free to 302 you to 127.0.0.1, so validating only the
        // URL the user typed proves nothing.
        $this->fakeDns();
        Http::fake([
            'https://recipes.example/r' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('recipes.import'), ['url' => 'https://recipes.example/r'])
            ->assertStatus(422)
            ->assertJsonPath('message', __('messages.import_blocked_host'));
    }

    /**
     * The size cap has to bite before the body is in memory.
     *
     * Buffering everything and measuring afterwards enforces nothing — by then a
     * multi-gigabyte response has already been allocated.
     */
    public function test_an_oversized_body_is_refused(): void
    {
        $this->fakeDns();
        $huge = str_repeat('x', SafeUrlFetcher::MAX_BYTES + 1024);

        Http::fake([
            'https://huge.example/*' => Http::response($huge, 200, ['Content-Type' => 'text/html']),
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('recipes.import'), ['url' => 'https://huge.example/big'])
            ->assertStatus(422)
            ->assertJsonPath('message', __('messages.import_too_large'));
    }

    public function test_a_lying_content_length_does_not_get_past_the_cap(): void
    {
        // Content-Length is a hint from the remote server, not a promise; the
        // streamed read is what actually enforces the limit.
        $this->fakeDns();
        $huge = str_repeat('x', SafeUrlFetcher::MAX_BYTES + 1024);

        Http::fake([
            'https://liar.example/*' => Http::response($huge, 200, [
                'Content-Type'   => 'text/html',
                'Content-Length' => '10',
            ]),
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('recipes.import'), ['url' => 'https://liar.example/big'])
            ->assertStatus(422)
            ->assertJsonPath('message', __('messages.import_too_large'));
    }

    // ── Parsing ──────────────────────────────────────────────────────────

    public function test_it_reads_a_schema_org_recipe(): void
    {
        $this->fakeDns();
        Http::fake([
            'https://recipes.example/*' => Http::response($this->page([
                'name'               => 'Lemon Chicken',
                'description'        => 'Bright and simple.',
                'recipeYield'        => '4 servings',
                'prepTime'           => 'PT20M',
                'cookTime'           => 'PT1H10M',
                'recipeIngredient'   => ['500 g chicken', '2 lemons', 'salt'],
                'recipeInstructions' => [
                    ['@type' => 'HowToStep', 'text' => '1. Season the chicken.'],
                    ['@type' => 'HowToStep', 'text' => '2. Roast for an hour.'],
                ],
            ]), 200, ['Content-Type' => 'text/html']),
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->postJson(route('recipes.import'), ['url' => 'https://recipes.example/lemon-chicken']);

        $response->assertOk();
        $data = $response->json('data');

        $this->assertSame('Lemon Chicken', $data['name']);
        $this->assertSame(4, $data['servings']);
        $this->assertSame(20, $data['prep_minutes']);
        $this->assertSame(70, $data['cook_minutes'], 'PT1H10M is 70 minutes');
        $this->assertTrue($data['matched']);

        // The site numbers its own steps; the UI renumbers, so they're stripped.
        $this->assertSame(
            [
                ['section' => null, 'text' => 'Season the chicken.'],
                ['section' => null, 'text' => 'Roast for an hour.'],
            ],
            $data['steps']
        );

        // assertEquals, not assertSame: a whole-number float survives the JSON
        // round trip as an int, and the numeric type isn't what's under test.
        $this->assertEquals(
            [
                ['name' => 'chicken', 'quantity' => 500, 'unit' => 'g',     'section' => null],
                ['name' => 'lemons',  'quantity' => 2,   'unit' => 'piece', 'section' => null],
                ['name' => 'salt',    'quantity' => 1,   'unit' => 'piece', 'section' => null],
            ],
            $data['ingredients']
        );
    }

    public function test_it_finds_a_recipe_nested_in_an_at_graph(): void
    {
        $graph = json_encode([
            '@context' => 'https://schema.org',
            '@graph'   => [
                ['@type' => 'WebSite', 'name' => 'Some Blog'],
                ['@type' => ['Recipe'], 'name' => 'Fasolada', 'recipeIngredient' => ['1 kg beans']],
            ],
        ]);

        $this->fakeDns();
        Http::fake([
            'https://blog.example/*' => Http::response(
                "<html><head><script type=\"application/ld+json\">{$graph}</script></head><body></body></html>",
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $data = $this->actingAs(User::factory()->create())
            ->postJson(route('recipes.import'), ['url' => 'https://blog.example/fasolada'])
            ->assertOk()
            ->json('data');

        $this->assertSame('Fasolada', $data['name']);
        $this->assertSame('beans', $data['ingredients'][0]['name']);
    }

    public function test_it_falls_back_to_opengraph_and_says_so(): void
    {
        $this->fakeDns();
        Http::fake([
            'https://plain.example/*' => Http::response(
                '<html><head><meta property="og:title" content="Grandma\'s Pie"></head><body></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $data = $this->actingAs(User::factory()->create())
            ->postJson(route('recipes.import'), ['url' => 'https://plain.example/pie'])
            ->assertOk()
            ->json('data');

        $this->assertSame("Grandma's Pie", $data['name']);
        $this->assertFalse($data['matched'], 'The UI needs to know this was a fallback, not a parsed recipe.');
        $this->assertSame([], $data['ingredients']);
    }

    public function test_a_page_with_nothing_usable_reports_a_clear_failure(): void
    {
        $this->fakeDns();
        Http::fake([
            'https://empty.example/*' => Http::response('<html><head></head><body>hi</body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('recipes.import'), ['url' => 'https://empty.example/nothing'])
            ->assertStatus(422)
            ->assertJsonPath('message', __('messages.import_no_recipe_found'));
    }

    public function test_import_requires_authentication(): void
    {
        $this->postJson(route('recipes.import'), ['url' => 'https://recipes.example/x'])
            ->assertUnauthorized();
    }

    // ── Sections & units ─────────────────────────────────────────────────

    public function test_it_keeps_the_headings_a_method_is_written_under(): void
    {
        $this->fakeDns();
        Http::fake([
            'https://sectioned.example/*' => Http::response($this->page([
                'name'               => 'Cheesecake',
                'recipeIngredient'   => [
                    'Για τη βάση:', '200 γρ. μπισκότα', '100 γρ. βούτυρο',
                    'Για τη σαντιγί', '500 γρ. κρέμα γάλακτος', '1 κ.γ. βανίλια',
                ],
                'recipeInstructions' => [
                    [
                        '@type' => 'HowToSection', 'name' => 'Για τη βάση',
                        'itemListElement' => [
                            ['@type' => 'HowToStep', 'text' => '1. Θρυμματίζουμε τα μπισκότα.'],
                            ['@type' => 'HowToStep', 'text' => '2. Προσθέτουμε το βούτυρο.'],
                        ],
                    ],
                    [
                        '@type' => 'HowToSection', 'name' => 'Για τη σαντιγί',
                        'itemListElement' => [
                            ['@type' => 'HowToStep', 'text' => 'Χτυπάμε την κρέμα.'],
                        ],
                    ],
                ],
            ]), 200, ['Content-Type' => 'text/html']),
        ]);

        $data = $this->actingAs(User::factory()->create())
            ->postJson(route('recipes.import'), ['url' => 'https://sectioned.example/cheesecake'])
            ->assertOk()
            ->json('data');

        $this->assertSame(
            [
                ['section' => 'Για τη βάση',    'text' => 'Θρυμματίζουμε τα μπισκότα.'],
                ['section' => 'Για τη βάση',    'text' => 'Προσθέτουμε το βούτυρο.'],
                ['section' => 'Για τη σαντιγί', 'text' => 'Χτυπάμε την κρέμα.'],
            ],
            $data['steps']
        );

        // Ingredient groups are smuggled in as heading-shaped list entries.
        $this->assertSame(
            ['Για τη βάση', 'Για τη βάση', 'Για τη σαντιγί', 'Για τη σαντιγί'],
            array_column($data['ingredients'], 'section')
        );
        // …and the headings themselves must not become ingredients.
        $this->assertSame(
            ['μπισκότα', 'βούτυρο', 'κρέμα γάλακτος', 'βανίλια'],
            array_column($data['ingredients'], 'name')
        );
    }

    public function test_greek_units_are_canonicalised_on_import(): void
    {
        $this->fakeDns();
        Http::fake([
            'https://units.example/*' => Http::response($this->page([
                'name'             => 'Δοκιμή',
                'recipeIngredient' => [
                    '100 γρ.  ζάχαρη', '1 κ.γ.  βανίλια', '2 κ.σ. ελαιόλαδο',
                    '1/2 φλιτζάνι γάλα', '1 κιλό πατάτες', '3 σκελίδες σκόρδο',
                    '2 λεμόνια',
                ],
            ]), 200, ['Content-Type' => 'text/html']),
        ]);

        $data = $this->actingAs(User::factory()->create())
            ->postJson(route('recipes.import'), ['url' => 'https://units.example/x'])
            ->assertOk()
            ->json('data');

        $this->assertSame(
            ['g', 'tsp', 'tbsp', 'cup', 'kg', 'clove', 'piece'],
            array_column($data['ingredients'], 'unit')
        );
        // "2 λεμόνια" has no unit word, so the count stays whole and the name intact.
        $this->assertSame('λεμόνια', $data['ingredients'][6]['name']);
        $this->assertEquals(0.5, $data['ingredients'][3]['quantity'], 'Fractions are parsed');
    }

    public function test_sections_survive_a_save_and_reload(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('recipes.store'), [
            'name'        => 'Cheesecake',
            'servings'    => 8,
            'ingredients' => [
                ['section' => 'Για τη βάση',    'name' => 'μπισκότα', 'quantity' => 200, 'unit' => 'g'],
                ['section' => 'Για τη σαντιγί', 'name' => 'κρέμα',    'quantity' => 500, 'unit' => 'g'],
            ],
            'steps' => [
                ['section' => 'Για τη βάση',    'text' => 'Θρυμματίζουμε.'],
                ['section' => 'Για τη σαντιγί', 'text' => 'Χτυπάμε.'],
                ['section' => 'Για τη σαντιγί', 'text' => 'Απλώνουμε.'],
            ],
        ])->assertCreated();

        $recipe = Recipe::where('name', 'Cheesecake')->firstOrFail()->load(['ingredients', 'steps']);

        $this->assertTrue($recipe->hasSections());
        $this->assertSame(['Για τη βάση', 'Για τη σαντιγί'], $recipe->stepsBySection()->keys()->all());
        $this->assertSame(['Για τη βάση', 'Για τη σαντιγί'], $recipe->ingredientsBySection()->keys()->all());
        // Two steps in the second part, in the order they were sent.
        $this->assertSame(
            ['Χτυπάμε.', 'Απλώνουμε.'],
            $recipe->stepsBySection()['Για τη σαντιγί']->pluck('text')->all()
        );
    }

    public function test_an_ungrouped_recipe_stores_null_not_an_empty_string(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('recipes.store'), [
            'name'        => 'Simple',
            'ingredients' => [['section' => '', 'name' => 'salt', 'quantity' => 1, 'unit' => 'pinch']],
            'steps'       => [['section' => '', 'text' => 'Season.']],
        ])->assertCreated();

        $recipe = Recipe::where('name', 'Simple')->firstOrFail();

        $this->assertNull($recipe->ingredients->first()->section);
        $this->assertNull($recipe->steps->first()->section);
        $this->assertFalse($recipe->fresh()->load(['ingredients', 'steps'])->hasSections());
    }

    public function test_a_hand_typed_unit_is_canonicalised_on_save(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('recipes.store'), [
            'name'        => 'Mixed units',
            'ingredients' => [
                ['name' => 'ζάχαρη',  'quantity' => 100, 'unit' => 'γρ.'],
                ['name' => 'βανίλια', 'quantity' => 1,   'unit' => 'κ.γ.'],
                ['name' => 'flour',   'quantity' => 2,   'unit' => 'CUPS'],
            ],
        ])->assertCreated();

        $recipe = Recipe::where('name', 'Mixed units')->firstOrFail();

        $this->assertSame(['g', 'tsp', 'cup'], $recipe->ingredients->pluck('unit')->all());
    }

    // ── Site adapters ────────────────────────────────────────────────────
    // Some sites publish flat JSON-LD while their own page data keeps the
    // section structure. Adapters read that, and must degrade quietly.

    private function nextDataPage(array $recipeData, array $jsonLd = []): string
    {
        $next = json_encode(['props' => ['pageProps' => ['ssRecipe' => ['data' => $recipeData]]]]);
        $ld   = $this->page($jsonLd + ['name' => 'Μιλφέιγ']);

        return str_replace('</head>', '<script id="__NEXT_DATA__" type="application/json">' . $next . '</script></head>', $ld);
    }

    public function test_it_reads_sections_a_site_hides_outside_json_ld(): void
    {
        $this->fakeDns();
        Http::fake([
            'https://next.example/*' => Http::response($this->nextDataPage([
                'ingredient_sections' => [
                    ['title' => 'Για τα φύλλα ', 'ingredients' => [
                        ['title' => 'φύλλο σφολιάτας', 'quantity' => '1',   'unit' => '',       'info' => '425 γρ.'],
                        ['title' => 'ζάχαρη',          'quantity' => '100', 'unit' => 'γρ. ',   'info' => ''],
                    ]],
                    ['title' => 'Για την κρέμα ', 'ingredients' => [
                        ['title' => 'βανίλια', 'quantity' => '1', 'unit' => 'κ.γ. ', 'info' => ''],
                    ]],
                ],
                'method' => [
                    ['section' => 'Για τα φύλλα', 'steps' => [
                        ['step' => 'Προθερμαίνουμε τον φούρνο στους 190&deg;C.'],
                        ['step' => 'Στρώνουμε λαδόκολλα.'],
                    ]],
                    ['section' => 'Για την κρέμα', 'steps' => [['step' => 'Χτυπάμε την κρέμα.']]],
                ],
            ]), 200, ['Content-Type' => 'text/html']),
        ]);

        $data = $this->actingAs(User::factory()->create())
            ->postJson(route('recipes.import'), ['url' => 'https://next.example/milfeig'])
            ->assertOk()
            ->json('data');

        $this->assertSame(
            ['Για τα φύλλα', 'Για τα φύλλα', 'Για την κρέμα'],
            array_column($data['ingredients'], 'section')
        );
        // quantity and unit arrive as separate fields, so nothing is recovered
        // from a string — and the units still land on canonical keys.
        $this->assertSame(['piece', 'g', 'tsp'], array_column($data['ingredients'], 'unit'));
        // `info` is a supplementary amount, not the quantity: it rides in the name.
        $this->assertSame('φύλλο σφολιάτας (425 γρ.)', $data['ingredients'][0]['name']);

        $this->assertSame(
            [
                ['section' => 'Για τα φύλλα', 'text' => 'Προθερμαίνουμε τον φούρνο στους 190°C.'],
                ['section' => 'Για τα φύλλα', 'text' => 'Στρώνουμε λαδόκολλα.'],
                ['section' => 'Για την κρέμα', 'text' => 'Χτυπάμε την κρέμα.'],
            ],
            $data['steps'],
            'Entities are decoded and headings preserved'
        );
    }

    public function test_a_broken_adapter_payload_falls_back_to_json_ld(): void
    {
        // The blob is present but malformed — the shape a site rebuild produces.
        // The import must still succeed on the standard markup.
        $this->fakeDns();
        $html = $this->page([
            'name'               => 'Fallback Pie',
            'recipeIngredient'   => ['200 g flour'],
            'recipeInstructions' => [['@type' => 'HowToStep', 'text' => 'Mix it.']],
        ]);
        $html = str_replace('</head>', '<script id="__NEXT_DATA__" type="application/json">{not json</script></head>', $html);

        Http::fake(['https://broken.example/*' => Http::response($html, 200, ['Content-Type' => 'text/html'])]);

        $data = $this->actingAs(User::factory()->create())
            ->postJson(route('recipes.import'), ['url' => 'https://broken.example/pie'])
            ->assertOk()
            ->json('data');

        $this->assertSame('Fallback Pie', $data['name']);
        $this->assertSame('flour', $data['ingredients'][0]['name']);
        $this->assertSame([['section' => null, 'text' => 'Mix it.']], $data['steps']);
    }

    // ── Photos ───────────────────────────────────────────────────────────

    public function test_an_uploaded_photo_is_stored_and_re_encoded_as_jpeg(): void
    {
        Storage::fake('public');

        $response = $this->actingAs(User::factory()->create())
            ->post(route('recipes.image.upload'), [
                'image' => UploadedFile::fake()->image('dinner.png', 2000, 1500),
            ]);

        $response->assertCreated();
        $path = $response->json('path');

        $this->assertStringEndsWith('.jpg', $path, 'Everything is normalised to JPEG.');
        Storage::disk('public')->assertExists($path);
    }

    public function test_a_non_image_masquerading_as_one_is_rejected(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->create())
            ->postJson(route('recipes.image.upload'), [
                'image' => UploadedFile::fake()->createWithContent('evil.jpg', '<?php echo "pwned";'),
            ])
            ->assertStatus(422);
    }

    public function test_replacing_a_photo_deletes_the_old_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        // Real stored names, since `image_path` is now validated against the
        // exact shape RecipeImageStore mints.
        $old = 'recipes/' . str_repeat('a', 32) . '.jpg';
        $new = 'recipes/' . str_repeat('b', 32) . '.jpg';

        Storage::disk('public')->put($old, 'x');
        $recipe = Recipe::create([
            'user_id' => $user->id, 'name' => 'Stew', 'servings' => 2,
            'image_path' => $old,
        ]);
        Storage::disk('public')->put($new, 'y');

        $this->actingAs($user)->putJson(route('recipes.update', $recipe), [
            'name'        => 'Stew',
            'image_path'  => $new,
            'ingredients' => [['name' => 'beef', 'quantity' => 1, 'unit' => 'kg']],
        ])->assertOk();

        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($new);
    }

    public function test_deleting_a_recipe_removes_its_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $path = 'recipes/' . str_repeat('c', 32) . '.jpg';
        Storage::disk('public')->put($path, 'x');
        $recipe = Recipe::create([
            'user_id' => $user->id, 'name' => 'Soup', 'servings' => 2,
            'image_path' => $path,
        ]);

        $this->actingAs($user)->delete(route('recipes.destroy', $recipe))->assertRedirect();

        Storage::disk('public')->assertMissing($path);
    }

    /**
     * `image_path` is posted back by the client, so it is untrusted input.
     *
     * A prefix check ("does it start with recipes/") accepts
     * `recipes/../avatars/victim.jpg`, which normalises back inside the disk root
     * and let one user delete another's files.
     */
    public function test_a_traversal_image_path_is_rejected_on_save(): void
    {
        $user = User::factory()->create();

        foreach (['recipes/../avatars/victim.jpg', 'avatars/victim.jpg', '../../.env', 'recipes/x.jpg'] as $path) {
            $this->actingAs($user)->postJson(route('recipes.store'), [
                'name'        => 'Evil',
                'image_path'  => $path,
                'ingredients' => [['name' => 'x', 'quantity' => 1, 'unit' => 'piece']],
            ])->assertStatus(422, "Expected {$path} to be refused");
        }

        // The shape the app actually mints is accepted.
        $this->actingAs($user)->postJson(route('recipes.store'), [
            'name'        => 'Fine',
            'image_path'  => 'recipes/' . str_repeat('a', 32) . '.jpg',
            'ingredients' => [['name' => 'x', 'quantity' => 1, 'unit' => 'piece']],
        ])->assertCreated();
    }

    public function test_delete_refuses_a_traversal_path(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('secret.txt', 'TOP SECRET');
        Storage::disk('public')->put('avatars/victim.jpg', 'x');

        $store = app(\App\Services\RecipeImageStore::class);
        $store->delete('recipes/../secret.txt');
        $store->delete('recipes/../avatars/victim.jpg');

        Storage::disk('public')->assertExists('secret.txt');
        Storage::disk('public')->assertExists('avatars/victim.jpg');
    }

    /**
     * A decompression bomb is small on the wire and enormous once decoded, so a
     * byte-size limit does not stop it — the dimensions have to be checked from
     * the header before any decoder allocates a buffer.
     */
    public function test_an_image_with_absurd_dimensions_is_refused_before_decoding(): void
    {
        Storage::fake('public');

        // A valid 1×1 PNG with the header rewritten to claim 30000×30000.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $bomb = substr_replace($png, pack('N', 30000) . pack('N', 30000), 16, 8);

        $this->actingAs(User::factory()->create())
            ->postJson(route('recipes.image.upload'), [
                'image' => UploadedFile::fake()->createWithContent('bomb.png', $bomb),
            ])
            ->assertStatus(422);
    }

    public function test_the_image_store_will_not_delete_outside_its_own_directory(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/someone.jpg', 'x');

        app(\App\Services\RecipeImageStore::class)->delete('avatars/someone.jpg');

        Storage::disk('public')->assertExists('avatars/someone.jpg');
    }
}
