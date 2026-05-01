<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoppingListTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function user_can_create_shopping_list()
    {
        $response = $this->actingAs($this->user)->postJson('/api/shopping-lists', [
            'name' => 'Grocery Shopping',
            'description' => 'Weekly groceries',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['id', 'name', 'description', 'user_id']);
        $this->assertDatabaseHas('shopping_lists', [
            'name' => 'Grocery Shopping',
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function user_can_retrieve_own_shopping_lists()
    {
        $list = ShoppingList::factory()->create(['user_id' => $this->user->id]);
        
        $response = $this->actingAs($this->user)->getJson('/api/shopping-lists');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'links']);
    }

    /** @test */
    public function user_cannot_see_other_users_lists()
    {
        $otherUser = User::factory()->create();
        $list = ShoppingList::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)->getJson("/api/shopping-lists/{$list->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function user_can_add_item_to_shopping_list()
    {
        $list = ShoppingList::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->postJson(
            "/api/shopping-lists/{$list->id}/items",
            [
                'name' => 'Coca Cola',
                'quantity' => 2,
                'unit' => 'bottles',
            ]
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('shopping_list_items', [
            'shopping_list_id' => $list->id,
            'name' => 'Coca Cola',
        ]);
    }

    /** @test */
    public function user_can_update_shopping_list_item()
    {
        $list = ShoppingList::factory()->create(['user_id' => $this->user->id]);
        $item = ShoppingListItem::factory()->create(['shopping_list_id' => $list->id]);

        $response = $this->actingAs($this->user)->putJson(
            "/api/shopping-lists/{$list->id}/items/{$item->id}",
            [
                'name' => 'Updated Item',
                'quantity' => 5,
                'unit' => 'kg',
            ]
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('shopping_list_items', [
            'id' => $item->id,
            'name' => 'Updated Item',
        ]);
    }

    /** @test */
    public function user_can_delete_shopping_list_item()
    {
        $list = ShoppingList::factory()->create(['user_id' => $this->user->id]);
        $item = ShoppingListItem::factory()->create(['shopping_list_id' => $list->id]);

        $response = $this->actingAs($this->user)->deleteJson(
            "/api/shopping-lists/{$list->id}/items/{$item->id}"
        );

        $response->assertStatus(204);
        $this->assertDatabaseMissing('shopping_list_items', ['id' => $item->id]);
    }

    /** @test */
    public function user_can_toggle_item_checked_status()
    {
        $list = ShoppingList::factory()->create(['user_id' => $this->user->id]);
        $item = ShoppingListItem::factory()->create([
            'shopping_list_id' => $list->id,
            'checked' => false,
        ]);

        $response = $this->actingAs($this->user)->patchJson(
            "/api/shopping-lists/{$list->id}/items/{$item->id}/toggle"
        );

        $response->assertStatus(200);
        $this->assertTrue($response->json('checked'));
    }

    /** @test */
    public function user_can_update_shopping_list()
    {
        $list = ShoppingList::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->putJson(
            "/api/shopping-lists/{$list->id}",
            [
                'name' => 'Updated List Name',
                'description' => 'Updated description',
                'is_completed' => true,
            ]
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('shopping_lists', [
            'id' => $list->id,
            'name' => 'Updated List Name',
        ]);
    }

    /** @test */
    public function user_can_delete_shopping_list()
    {
        $list = ShoppingList::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->deleteJson("/api/shopping-lists/{$list->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('shopping_lists', ['id' => $list->id]);
    }

    /** @test */
    public function user_can_view_shopping_list_page()
    {
        $list = ShoppingList::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->get("/shopping-lists/{$list->id}");

        $response->assertStatus(200);
        $response->assertViewIs('shopping-list.show');
    }

    /** @test */
    public function unauthenticated_user_cannot_access_shopping_lists()
    {
        $response = $this->getJson('/api/shopping-lists');

        $response->assertStatus(401);
    }
}
