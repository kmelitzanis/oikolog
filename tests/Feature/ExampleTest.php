<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk();
    }

    public function test_the_dashboard_renders_for_a_signed_in_user(): void
    {
        $this->actingAs(User::factory()->create(['currency_code' => 'EUR']))
            ->get('/')
            ->assertOk();
    }
}
