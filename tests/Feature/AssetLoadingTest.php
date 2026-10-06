<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Pages must follow a running Vite dev server even when an older build is
 * still on disk — Tailwind only ships the classes it saw at build time, so the
 * stale build rendered new markup half-styled.
 */
class AssetLoadingTest extends TestCase
{
    private string $hot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hot = public_path('hot');
    }

    protected function tearDown(): void
    {
        @unlink($this->hot);
        parent::tearDown();
    }

    public function test_a_running_dev_server_wins_over_the_last_build(): void
    {
        file_put_contents($this->hot, 'http://127.0.0.1:5173');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('http://127.0.0.1:5173/@vite/client', false)
            ->assertDontSee('/build/assets/', false);
    }

    public function test_without_a_dev_server_the_built_assets_are_used(): void
    {
        @unlink($this->hot);

        $response = $this->get(route('login'))->assertOk();

        if (is_file(public_path('build/manifest.json'))) {
            $response->assertSee('/build/assets/', false);
        }
        $response->assertDontSee('@vite/client', false);
    }
}
