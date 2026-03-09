<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_transfer_ownership()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        // Owner creates a family
        $this->actingAs($owner)->post(route('family.create'), ['name' => 'Team X']);

        $family = Family::first();
        $this->assertNotNull($family);

        // Add member to family
        $member->update(['family_id' => $family->id, 'family_role' => 'member']);

        // Transfer ownership to member
        $resp = $this->actingAs($owner)->post(route('family.transfer', $member));
        $resp->assertRedirect();

        $owner->refresh();
        $member->refresh();
        $family->refresh();

        $this->assertEquals('admin', $owner->family_role);
        $this->assertEquals('owner', $member->family_role);
        $this->assertEquals($member->id, $family->owner_id);
    }

    public function test_only_owner_can_remove_member()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $other = User::factory()->create();

        // Owner creates a family
        $this->actingAs($owner)->post(route('family.create'), ['name' => 'Team X']);
        $family = Family::first();

        // Add member and another member
        $member->update(['family_id' => $family->id, 'family_role' => 'member']);
        $other->update(['family_id' => $family->id, 'family_role' => 'member']);

        // Non-owner cannot remove
        $resp = $this->actingAs($member)->delete(route('family.remove', $other));
        $resp->assertStatus(403);

        // Owner removes
        $resp2 = $this->actingAs($owner)->delete(route('family.remove', $other));
        $resp2->assertRedirect();
        $other->refresh();
        $this->assertNull($other->family_id);
    }
}

