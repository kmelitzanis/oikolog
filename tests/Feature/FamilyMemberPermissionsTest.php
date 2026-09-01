<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Category;
use App\Models\Family;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A plain family member is not a read-only user.
 *
 * The only thing the app withholds from them is the Admin section (categories,
 * providers, products, users) and removing someone from the family. Sharing a
 * bill with the household means the household can manage it.
 */
class FamilyMemberPermissionsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: User, 2: Bill} owner, plain member, shared bill */
    private function household(): array
    {
        $owner = User::factory()->create(['currency_code' => 'EUR']);
        $family = Family::create(['name' => 'Test', 'owner_id' => $owner->id]);
        $owner->update(['family_id' => $family->id, 'family_role' => 'owner']);

        $member = User::factory()->create([
            'currency_code' => 'EUR',
            'family_id'     => $family->id,
            'family_role'   => 'member',
            'is_admin'      => false,
        ]);

        $bill = Bill::create([
            'name'          => 'ΔΕΗ',
            'category_id'   => Category::create(['name' => 'Utilities'])->id,
            'created_by'    => $owner->id,
            'family_id'     => $family->id,
            'is_shared'     => true,
            'amount'        => 100,
            'cost_varies'   => true,
            'currency_code' => 'EUR',
            'frequency'     => 'monthly',
            'start_date'    => now()->subMonth()->toDateString(),
            'next_due_date' => now()->addDays(5)->toDateString(),
            'is_active'     => true,
        ]);

        return [$owner->refresh(), $member, $bill];
    }

    public function test_a_member_can_edit_a_shared_bill_they_did_not_create(): void
    {
        [, $member, $bill] = $this->household();

        $this->actingAs($member)->get(route('bills.edit', $bill))->assertOk();

        $this->actingAs($member)->put(route('bills.update', $bill), [
            'name'        => 'ΔΕΗ (renamed)',
            'category_id' => $bill->category_id,
            'amount'      => '120',
            'frequency'   => 'monthly',
            'start_date'  => $bill->start_date->toDateString(),
        ])->assertRedirect();

        $this->assertSame('ΔΕΗ (renamed)', $bill->fresh()->name);
    }

    public function test_a_member_can_set_the_period_amount_inline(): void
    {
        [, $member, $bill] = $this->household();

        $this->actingAs($member)
            ->patchJson(route('bills.amount', $bill), ['current_amount' => '108'])
            ->assertOk();

        $this->assertSame(108.0, $bill->fresh()->periodAmount());
    }

    public function test_a_member_can_delete_a_shared_bill(): void
    {
        [, $member, $bill] = $this->household();

        $this->actingAs($member)->delete(route('bills.destroy', $bill))->assertRedirect();

        $this->assertNull(Bill::find($bill->id));
    }

    public function test_a_member_sees_the_invite_code(): void
    {
        [, $member] = $this->household();

        $this->actingAs($member)->get(route('family.index'))->assertOk()
            ->assertSee($member->fresh()->family->invite_code);
    }

    public function test_a_member_can_regenerate_the_invite_code(): void
    {
        [, $member] = $this->household();
        $before = $member->fresh()->family->invite_code;

        $this->actingAs($member)->post(route('family.regenerate'))->assertRedirect();

        $this->assertNotSame($before, $member->fresh()->family->invite_code);
    }

    // ── Still withheld ──────────────────────────────────────────────────

    public function test_a_member_stays_out_of_the_admin_section(): void
    {
        [, $member] = $this->household();

        foreach (['admin.categories.index', 'admin.providers.index',
                  'admin.products.index', 'admin.users.index'] as $route) {
            $this->actingAs($member)->get(route($route))->assertForbidden();
        }
    }

    public function test_a_member_cannot_remove_someone_from_the_family(): void
    {
        [$owner, $member] = $this->household();

        $this->actingAs($member)->delete(route('family.remove', $owner))->assertForbidden();

        $this->assertNotNull($owner->fresh()->family_id);
    }

    public function test_a_stranger_still_cannot_touch_the_bill(): void
    {
        [, , $bill] = $this->household();
        $stranger = User::factory()->create(['currency_code' => 'EUR']);

        $this->actingAs($stranger)->get(route('bills.edit', $bill))->assertForbidden();
        $this->actingAs($stranger)
            ->patchJson(route('bills.amount', $bill), ['current_amount' => '1'])
            ->assertForbidden();
    }

    public function test_a_private_bill_stays_private_from_the_family(): void
    {
        [$owner, $member] = $this->household();

        $private = Bill::create([
            'name'          => 'Personal',
            'category_id'   => Category::create(['name' => 'Other'])->id,
            'created_by'    => $owner->id,
            'is_shared'     => false,
            'amount'        => 10,
            'currency_code' => 'EUR',
            'frequency'     => 'monthly',
            'start_date'    => now()->toDateString(),
            'next_due_date' => now()->toDateString(),
            'is_active'     => true,
        ]);

        $this->actingAs($member)->get(route('bills.show', $private))->assertForbidden();
        $this->actingAs($member)->get(route('bills.edit', $private))->assertForbidden();
    }
}
