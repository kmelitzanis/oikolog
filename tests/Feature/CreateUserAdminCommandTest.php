<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateUserAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_make_user_admin_command_creates_user()
    {
        $email = 'cliadmin@example.com';

        $this->artisan('make:user:admin', [
            '--email' => $email,
            '--password' => 'supersecret123',
            '--name' => 'CLI Admin',
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => $email]);

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertEquals('owner', $user->family_role);
    }
}

