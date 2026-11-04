<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The admin's provider pages, end to end: they had no coverage at all. */
class AdminProviderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_the_create_form_renders(): void
    {
        Category::create(['name' => 'Τηλεφωνία']);

        $this->actingAs($this->admin())->get(route('admin.providers.create'))->assertOk();
    }

    public function test_a_provider_is_created_with_its_categories(): void
    {
        Storage::fake('public');
        $phone = Category::create(['name' => 'Τηλεφωνία']);
        $internet = Category::create(['name' => 'Internet']);

        $this->actingAs($this->admin())->post(route('admin.providers.store'), [
            'name'         => 'Κωτσόβολος',
            'category_ids' => [$phone->id, $internet->id],
            'website'      => 'https://www.kotsovolos.gr',
            'phone'        => '2102899999',
            'logo'         => UploadedFile::fake()->image('logo.png', 64, 64),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $provider = Provider::firstWhere('name', 'Κωτσόβολος');
        $this->assertNotNull($provider);
        $this->assertEqualsCanonicalizing([$phone->id, $internet->id], $provider->categories->pluck('id')->all());
    }

    public function test_the_list_and_edit_pages_render(): void
    {
        $category = Category::create(['name' => 'Τηλεφωνία']);
        $provider = Provider::create(['name' => 'Cosmote']);
        $provider->categories()->sync([$category->id]);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.providers.index'))->assertOk()->assertSee('Cosmote');
        $this->actingAs($admin)->get(route('admin.providers.edit', $provider))->assertOk();
    }

    public function test_a_provider_is_updated(): void
    {
        $category = Category::create(['name' => 'Τηλεφωνία']);
        $provider = Provider::create(['name' => 'Cosmote']);
        $provider->categories()->sync([$category->id]);

        $this->actingAs($this->admin())->put(route('admin.providers.update', $provider), [
            'name' => 'Cosmote Mobile', 'category_ids' => [$category->id],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Cosmote Mobile', $provider->fresh()->name);
    }
}
