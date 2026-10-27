<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The fuel log: fill-ups are fuel expenses, consumption is measured from one
 * full tank to the next, and the totals stay one set of figures.
 */
class VehicleFuelLogTest extends TestCase
{
    use RefreshDatabase;

    private function vehicle(User $user): Vehicle
    {
        return Vehicle::create([
            'name' => 'Toyota Yaris', 'type' => 'car', 'odometer_km' => 50_000,
            'purchase_km' => 10_000, 'purchase_date' => now()->subYears(2), 'created_by' => $user->id,
        ]);
    }

    private function fill(Vehicle $vehicle, string $date, ?int $km, ?float $litres, float $amount, bool $full = true): VehicleExpense
    {
        return $vehicle->expenses()->create([
            'category' => 'fuel', 'spent_at' => $date, 'odometer_km' => $km,
            'litres' => $litres, 'amount' => $amount, 'is_full_tank' => $full,
        ]);
    }

    public function test_consumption_runs_from_full_tank_to_full_tank(): void
    {
        $vehicle = $this->vehicle(User::factory()->create());

        $first = $this->fill($vehicle, '2026-09-01', 50_000, 40, 70);
        // A top-up in between belongs to the stretch the next full fill closes.
        $topUp = $this->fill($vehicle, '2026-09-08', 50_300, 10, 18, full: false);
        $second = $this->fill($vehicle, '2026-09-15', 50_600, 26, 46);
        $third = $this->fill($vehicle, '2026-09-29', 51_000, 30, 54);

        $log = $vehicle->fresh()->fuelLog();

        $this->assertNull($log->consumptionFor($first), 'Nothing before it to measure from.');
        $this->assertNull($log->consumptionFor($topUp));
        // 10 + 26 litres over 600 km.
        $this->assertSame(6.0, $log->consumptionFor($second));
        // 30 litres over 400 km.
        $this->assertSame(7.5, $log->consumptionFor($third));
        // 66 litres over 1000 km, weighted by distance rather than averaged.
        $this->assertSame(6.6, $log->averageConsumption());
        // 18 + 46 + 54 over 1000 km.
        $this->assertSame(0.118, $log->costPerKm());
    }

    public function test_a_fill_up_without_a_reading_breaks_the_chain(): void
    {
        $vehicle = $this->vehicle(User::factory()->create());

        $this->fill($vehicle, '2026-09-01', 50_000, 40, 70);
        $this->fill($vehicle, '2026-09-08', null, 35, 60);
        $after = $this->fill($vehicle, '2026-09-15', 50_900, 40, 70);

        // The 35 litres went in unmeasured, so 900 km cannot be divided by 40.
        $this->assertNull($vehicle->fresh()->fuelLog()->consumptionFor($after));
    }

    public function test_the_total_is_worked_out_from_litres_and_price(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->vehicle($user);

        $this->actingAs($user)->post(route('vehicles.expenses.store', $vehicle), [
            'category' => 'fuel', 'spent_at' => '2026-10-20', 'odometer_km' => 50_450,
            'litres' => '38.5', 'price_per_litre' => '1.789', 'is_full_tank' => '0',
            'fuel_type' => 'petrol', 'station' => 'Shell Κηφισίας',
        ])->assertRedirect()->assertSessionHas('success', __('messages.vehicle_fuel_added'));

        $fill = $vehicle->expenses()->first();
        $this->assertSame('68.88', $fill->amount);
        $this->assertFalse($fill->is_full_tank);
        $this->assertSame('petrol', $fill->fuel_type);
        $this->assertSame('Shell Κηφισίας', $fill->station);
        // A fill-up is an odometer reading too.
        $this->assertSame(50_450, $vehicle->fresh()->odometer_km);
    }

    public function test_without_an_amount_or_a_price_it_is_refused(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->vehicle($user);

        $this->actingAs($user)->post(route('vehicles.expenses.store', $vehicle), [
            'category' => 'fuel', 'spent_at' => '2026-10-20', 'litres' => '38.5',
        ])->assertSessionHasErrors('amount');
    }

    public function test_a_plain_expense_drops_the_fuel_fields(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->vehicle($user);

        $this->actingAs($user)->post(route('vehicles.expenses.store', $vehicle), [
            'category' => 'tax', 'spent_at' => '2026-10-20', 'amount' => '180', 'station' => 'nope',
        ])->assertRedirect();

        $this->assertNull($vehicle->expenses()->first()->station);
    }

    public function test_fill_ups_still_count_in_the_vehicles_costs(): void
    {
        $vehicle = $this->vehicle(User::factory()->create());
        $this->fill($vehicle, now()->subDays(5)->toDateString(), 50_000, 40, 70);

        $this->assertSame(70.0, $vehicle->fresh()->yearCost());
        $this->assertSame(70.0, $vehicle->fresh()->expenseBreakdown()['fuel']['amount']);
    }

    public function test_the_vehicle_page_shows_the_fuel_log(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        $vehicle = $this->vehicle($user);
        $this->fill($vehicle, '2026-09-01', 50_000, 40, 70);
        $this->fill($vehicle, '2026-09-15', 50_600, 36, 64);

        $this->actingAs($user)->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee(__('messages.vehicle_section_fuel'))
            ->assertSee('6,0 L/100km')
            ->assertSee(__('messages.vehicle_add_fuel'));
    }
}
