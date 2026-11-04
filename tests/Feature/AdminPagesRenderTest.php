<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Every admin form opens: the provider one used to be a 500 before it was filled in. */
class AdminPagesRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_forms_render(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::create(['name' => 'Τηλεφωνία']);

        foreach ([
            route('admin.categories.index'), route('admin.categories.create'), route('admin.categories.edit', $category),
            route('admin.users.index'), route('admin.users.create'), route('admin.users.edit', $admin),
            route('admin.providers.index'), route('admin.providers.create'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }
}
