<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Category;
use App\Models\Provider;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recurring vehicle costs are bills: recorded on the car they become one, a
 * bill for the car counts as its cost, and neither is counted twice.
 */
class VehicleBillsTest extends TestCase
{
    use RefreshDatabase;

    private function vehicle(User $user, array $attrs = []): Vehicle
    {
        return Vehicle::create(array_merge([
            'name' => 'Toyota Yaris', 'type' => 'car', 'plate' => 'ΙΚΧ-1234', 'odometer_km' => 50_000,
            'purchase_km' => 10_000, 'purchase_date' => now()->subYears(2), 'created_by' => $user->id,
        ], $attrs));
    }

    public function test_the_vehicle_categories_come_with_their_providers(): void
    {
        foreach (['insurance', 'road_tax', 'kteo', 'tolls'] as $key) {
            $this->assertNotNull(Category::forVehicleExpense($key), $key);
        }

        $this->assertTrue(Category::forVehicleExpense('road_tax')->providers->contains('name', 'ΑΑΔΕ (AADE)'));
        $tolls = Category::forVehicleExpense('tolls')->providers->pluck('name');
        $this->assertTrue($tolls->contains('Αττική Οδός'));
        $this->assertTrue($tolls->contains('Γέφυρα Ρίου-Αντιρρίου'));
    }

    public function test_a_recurring_insurance_recorded_on_the_car_becomes_a_bill(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR']);
        $car = $this->vehicle($user);
        $insurer = Provider::create(['name' => 'Interamerican']);
        $insurer->categories()->attach(Category::forVehicleExpense('insurance'));

        $this->actingAs($user)->post(route('vehicles.expenses.store', $car), [
            'category' => 'insurance', 'amount' => '210', 'spent_at' => '2026-11-01',
            'provider_id' => $insurer->id, 'recurring' => '1', 'frequency' => 'semiannual',
        ])->assertRedirect()->assertSessionHas('success', __('messages.vehicle_expense_added_as_bill'));

        $bill = Bill::firstWhere('vehicle_id', $car->id);
        $this->assertNotNull($bill);
        $this->assertSame(Category::forVehicleExpense('insurance')->id, $bill->category_id);
        $this->assertSame($insurer->id, $bill->provider_id);
        $this->assertSame('semiannual', $bill->frequency);
        // This one is paid; the next comes six months on.
        $this->assertSame(1, $bill->payments()->count());
        $this->assertSame('2027-05-01', $bill->next_due_date->toDateString());

        // Counted once, through the bill, not again as a loose expense.
        $this->assertSame(0, $car->expenses()->count());
        $this->assertSame(210.0, $car->fresh()->totalSpent());
        $this->assertSame(210.0, $car->fresh()->expenseBreakdown()['insurance']['amount']);
    }

    public function test_a_one_off_toll_stays_a_vehicle_expense_with_its_operator(): void
    {
        $user = User::factory()->create();
        $car = $this->vehicle($user);
        $operator = Provider::firstWhere('name', 'Αττική Οδός');

        $this->actingAs($user)->post(route('vehicles.expenses.store', $car), [
            'category' => 'tolls', 'amount' => '2.80', 'spent_at' => '2026-11-01', 'provider_id' => $operator->id,
            // Tolls do not recur; the flag is ignored for them.
            'recurring' => '1', 'frequency' => 'monthly',
        ])->assertRedirect();

        $this->assertSame(0, Bill::count());
        $this->assertSame($operator->id, $car->expenses()->first()->provider_id);
    }

    public function test_recurring_needs_a_frequency(): void
    {
        $user = User::factory()->create();
        $car = $this->vehicle($user);

        $this->actingAs($user)->post(route('vehicles.expenses.store', $car), [
            'category' => 'kteo', 'amount' => '45', 'spent_at' => '2026-11-01', 'recurring' => '1',
        ])->assertSessionHasErrors('frequency');

        $this->assertSame(0, Bill::count());
    }

    public function test_a_road_tax_bill_for_the_car_shows_on_the_car(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR']);
        $car = $this->vehicle($user);

        $this->actingAs($user)->post(route('bills.store'), [
            'name' => 'Τέλη κυκλοφορίας 2027', 'category_id' => Category::forVehicleExpense('road_tax')->id,
            'amount' => '180', 'frequency' => 'yearly', 'start_date' => now()->toDateString(), 'vehicle_id' => $car->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $bill = Bill::firstWhere('name', 'Τέλη κυκλοφορίας 2027');
        $this->assertSame($car->id, $bill->vehicle_id);

        $this->actingAs($user)->post(route('bills.pay', $bill))->assertRedirect();

        $this->assertSame(180.0, $car->fresh()->expenseBreakdown()['road_tax']['amount']);
        $this->actingAs($user)->get(route('vehicles.show', $car))
            ->assertOk()
            ->assertSee('Τέλη κυκλοφορίας 2027')
            ->assertSee(route('bills.show', $bill), false);
    }

    public function test_a_vehicle_only_sticks_to_a_vehicle_category(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR']);
        $car = $this->vehicle($user);
        $other = Category::create(['name' => 'Ρεύμα']);

        $this->actingAs($user)->post(route('bills.store'), [
            'name' => 'ΔΕΗ', 'category_id' => $other->id, 'amount' => '80', 'frequency' => 'monthly',
            'start_date' => now()->toDateString(), 'vehicle_id' => $car->id,
        ])->assertSessionHasNoErrors();

        $this->assertNull(Bill::firstWhere('name', 'ΔΕΗ')->vehicle_id);
    }

    public function test_someone_elses_car_is_refused(): void
    {
        $user = User::factory()->create();
        $theirs = $this->vehicle(User::factory()->create());

        $this->actingAs($user)->post(route('bills.store'), [
            'name' => 'Ασφάλεια', 'category_id' => Category::forVehicleExpense('insurance')->id, 'amount' => '200',
            'frequency' => 'yearly', 'start_date' => now()->toDateString(), 'vehicle_id' => $theirs->id,
        ])->assertSessionHasErrors('vehicle_id');
    }

    public function test_the_vehicle_page_offers_providers_and_the_recurring_option(): void
    {
        $user = User::factory()->create(['locale' => 'el']);
        $car = $this->vehicle($user);

        $this->actingAs($user)->get(route('vehicles.show', $car))
            ->assertOk()
            ->assertSee('ΑΑΔΕ (AADE)')
            ->assertSee(__('messages.vehicle_recurring'))
            ->assertSee(__('messages.vehicle_expense_kteo'));
    }
}
