<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Income;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Shared with my family" must mean nothing when there is no family.
 *
 * The visibility rule compared family ids, and Eloquent turns a NULL in
 * `where('family_id', null)` into `IS NULL` — so a row marked shared by a
 * user without a family was readable, editable and payable by every other
 * user without one.
 */
class FamilyShareIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function loner(): User
    {
        return User::factory()->create(['currency_code' => 'EUR', 'family_id' => null]);
    }

    private function sharedBillWithoutFamily(User $owner): Bill
    {
        return Bill::create([
            'name'          => 'Owner rent',
            'category_id'   => Category::create(['name' => 'Home'])->id,
            'created_by'    => $owner->id,
            'family_id'     => null,
            'is_shared'     => true,
            'amount'        => 500,
            'currency_code' => 'EUR',
            'frequency'     => 'monthly',
            'start_date'    => now()->toDateString(),
            'next_due_date' => now()->addDays(3)->toDateString(),
            'is_active'     => true,
        ]);
    }

    public function test_a_familyless_shared_bill_stays_private(): void
    {
        $owner = $this->loner();
        $stranger = $this->loner();
        $bill = $this->sharedBillWithoutFamily($owner);

        $this->assertFalse(Bill::forUser($stranger)->whereKey($bill->id)->exists());
        $this->assertTrue(Bill::forUser($owner)->whereKey($bill->id)->exists());

        $this->actingAs($stranger)->get(route('bills.show', $bill))->assertForbidden();
        $this->actingAs($stranger)->get(route('bills.index', ['status' => 'all']))
            ->assertOk()->assertDontSee('Owner rent');
        $this->actingAs($stranger)->post(route('bills.pay', $bill))->assertForbidden();
        $this->actingAs($stranger)->delete(route('bills.destroy', $bill))->assertForbidden();
        $this->actingAs($stranger)->getJson("/api/bills/{$bill->id}")->assertForbidden();

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('bills', ['id' => $bill->id]);
    }

    public function test_other_shared_records_follow_the_same_rule(): void
    {
        $owner = $this->loner();
        $stranger = $this->loner();

        $account = Account::create([
            'name' => 'Savings', 'created_by' => $owner->id, 'is_shared' => true, 'family_id' => null,
        ]);
        $income = Income::create([
            'name' => 'Salary', 'amount' => 1000, 'currency_code' => 'EUR', 'frequency' => 'monthly',
            'start_date' => now()->toDateString(), 'next_date' => now()->toDateString(),
            'created_by' => $owner->id, 'is_shared' => true, 'family_id' => null, 'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'name' => 'Car', 'type' => 'car', 'odometer_km' => 0,
            'created_by' => $owner->id, 'is_shared' => true, 'family_id' => null, 'is_active' => true,
        ]);

        $this->actingAs($stranger)->get(route('accounts.show', $account))->assertForbidden();
        $this->actingAs($stranger)->get(route('income.show', $income))->assertForbidden();
        $this->actingAs($stranger)->get(route('vehicles.show', $vehicle))->assertForbidden();

        $this->assertFalse(Account::forUser($stranger)->exists());
        $this->assertFalse(Income::forUser($stranger)->exists());
        $this->assertFalse(Vehicle::forUser($stranger)->exists());
    }

    public function test_sharing_without_a_family_is_not_recorded(): void
    {
        $user = $this->loner();

        $this->actingAs($user)->post(route('bills.store'), [
            'name'        => 'Internet',
            'category_id' => Category::create(['name' => 'Utilities'])->id,
            'amount'      => 30,
            'frequency'   => 'monthly',
            'start_date'  => now()->toDateString(),
            'is_shared'   => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('bills', ['name' => 'Internet', 'is_shared' => false, 'family_id' => null]);
    }
}
