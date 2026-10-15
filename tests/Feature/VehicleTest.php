<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleTest extends TestCase
{
    use RefreshDatabase;

    private function vehicle(User $user, array $attrs = []): Vehicle
    {
        return Vehicle::create(array_merge([
            'name'          => 'Toyota Yaris',
            'type'          => 'car',
            'odometer_km'   => 84_320,
            'purchase_km'   => 12_000,
            'purchase_date' => now()->subYears(3),
            'created_by'    => $user->id,
        ], $attrs));
    }

    public function test_cost_per_km_is_null_until_distance_is_recorded(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->vehicle($user, ['odometer_km' => 500, 'purchase_km' => 500]);

        // Zero distance must not read as a measured rate of 0,00 per km.
        $this->assertNull($vehicle->costPerKm());
    }

    public function test_totals_combine_loose_expenses_and_service_receipts(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->vehicle($user);

        $vehicle->expenses()->create([
            'category' => 'fuel', 'amount' => 60, 'spent_at' => now()->subDays(3),
        ]);
        $vehicle->services()->create([
            'performed_at' => now()->subMonth(), 'total' => 240,
        ]);

        // A service is counted from its own table, never re-entered as an expense.
        $this->assertSame(300.0, $vehicle->yearCost());
        $this->assertSame(25.0, $vehicle->monthCost());
    }

    public function test_service_total_falls_back_to_the_sum_of_its_lines(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->vehicle($user);

        $service = $vehicle->services()->create([
            'performed_at' => now(),
            'total'        => 0,
            'lines'        => [['what' => 'Oil', 'cost' => 78], ['what' => 'Labour', 'cost' => 45]],
        ]);

        $this->assertSame(123.0, $service->effectiveTotal());
    }

    public function test_a_reminder_is_due_on_whichever_of_date_or_mileage_comes_first(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->vehicle($user);

        $reminder = $vehicle->reminders()->create([
            'label'    => 'Service',
            'due_date' => now()->addYear(),   // comfortably far off
            'due_km'   => 84_500,             // only 180 km away
        ]);
        $reminder->setRelation('vehicle', $vehicle);

        $this->assertSame('warn', $reminder->tone());
    }

    public function test_completing_a_recurring_reminder_opens_the_next_one(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->vehicle($user);

        $reminder = $vehicle->reminders()->create([
            'label'       => 'Oil change',
            'due_km'      => 90_000,
            'interval_km' => 15_000,
        ]);

        $this->actingAs($user)
            ->post(route('vehicles.reminders.complete', [$vehicle, $reminder]))
            ->assertRedirect();

        $this->assertNotNull($reminder->fresh()->completed_at);

        // The next one is measured from the odometer now, not from the old target.
        $next = $vehicle->reminders()->whereNull('completed_at')->first();
        $this->assertNotNull($next);
        $this->assertSame(84_320 + 15_000, $next->due_km);
    }

    public function test_a_reminder_with_no_date_and_no_mileage_is_rejected(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->vehicle($user);

        $this->actingAs($user)
            ->post(route('vehicles.reminders.store', $vehicle), ['label' => 'Someday'])
            ->assertSessionHasErrors('due_date');

        $this->assertSame(0, $vehicle->reminders()->count());
    }

    public function test_recording_a_service_drops_blank_lines_and_advances_the_odometer(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->vehicle($user);

        $this->actingAs($user)->post(route('vehicles.services.store', $vehicle), [
            'performed_at' => now()->toDateString(),
            'odometer_km'  => 85_000,
            'total'        => 120,
            'lines'        => [
                ['what' => 'Brake pads', 'cost' => '80'],
                ['what' => '', 'cost' => ''],
            ],
        ])->assertRedirect();

        $service = $vehicle->services()->first();
        $this->assertCount(1, $service->lines);
        $this->assertSame(85_000, $vehicle->fresh()->odometer_km);
    }

    public function test_a_stale_reading_never_winds_the_odometer_backwards(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->vehicle($user);

        $this->actingAs($user)->post(route('vehicles.expenses.store', $vehicle), [
            'category'    => 'fuel',
            'amount'      => 50,
            'spent_at'    => now()->subYear()->toDateString(),
            'odometer_km' => 60_000,
        ])->assertRedirect();

        $this->assertSame(84_320, $vehicle->fresh()->odometer_km);
    }

    public function test_another_households_vehicle_is_not_reachable(): void
    {
        $mine = User::factory()->create();
        $theirs = User::factory()->create();
        $vehicle = $this->vehicle($theirs);

        $this->actingAs($mine)->get(route('vehicles.show', $vehicle))->assertForbidden();
    }

    public function test_a_service_can_be_edited_and_deleted(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->vehicle($user);
        $service = $vehicle->services()->create(['performed_at' => now()->subMonth(), 'shop' => 'Old shop', 'total' => 100]);

        $this->actingAs($user)->put(route('vehicles.services.update', [$vehicle, $service]), [
            'performed_at' => now()->subMonth()->toDateString(),
            'shop'         => 'AUTOTEC',
            'total'        => 345,
            'lines'        => [['what' => 'Oil', 'cost' => ''], ['what' => '', 'cost' => '']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $service->refresh();
        $this->assertSame('AUTOTEC', $service->shop);
        $this->assertSame(345.0, (float) $service->total);
        $this->assertCount(1, $service->lines);

        $this->actingAs($user)->delete(route('vehicles.services.destroy', [$vehicle, $service]))->assertRedirect();
        $this->assertNull($service->fresh());
    }

    public function test_an_expense_can_be_edited_and_deleted(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->vehicle($user);
        $expense = $vehicle->expenses()->create([
            'category' => 'fuel', 'amount' => 50, 'spent_at' => now()->subDay(), 'created_by' => $user->id,
        ]);

        $this->actingAs($user)->put(route('vehicles.expenses.update', [$vehicle, $expense]), [
            'category' => 'insurance', 'amount' => 320, 'spent_at' => now()->toDateString(), 'note' => 'Yearly',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $expense->refresh();
        $this->assertSame('insurance', $expense->category);
        $this->assertSame(320.0, (float) $expense->amount);

        $this->actingAs($user)->delete(route('vehicles.expenses.destroy', [$vehicle, $expense]))->assertRedirect();
        $this->assertNull($expense->fresh());
    }

    public function test_a_reminder_can_be_edited_but_not_left_without_a_due(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->vehicle($user);
        $reminder = $vehicle->reminders()->create(['label' => 'Oil', 'due_km' => 90_000]);

        $this->actingAs($user)->put(route('vehicles.reminders.update', [$vehicle, $reminder]), [
            'label' => 'Oil & filter', 'due_km' => 95_000, 'interval_km' => 15_000,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Oil & filter', $reminder->fresh()->label);
        $this->assertSame(95_000, $reminder->fresh()->due_km);

        $this->actingAs($user)->put(route('vehicles.reminders.update', [$vehicle, $reminder]), ['label' => 'Oil'])
            ->assertSessionHasErrors('due_date');
    }

    public function test_entries_of_another_vehicle_cannot_be_edited_through_this_one(): void
    {
        $user = User::factory()->create();
        $mine = $this->vehicle($user);
        $other = $this->vehicle($user, ['name' => 'Other']);
        $expense = $other->expenses()->create([
            'category' => 'fuel', 'amount' => 50, 'spent_at' => now(), 'created_by' => $user->id,
        ]);

        $this->actingAs($user)->put(route('vehicles.expenses.update', [$mine, $expense]), [
            'category' => 'fuel', 'amount' => 1, 'spent_at' => now()->toDateString(),
        ])->assertNotFound();
    }

    public function test_the_page_lists_every_entry_with_edit_and_delete(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        $vehicle = $this->vehicle($user);
        $service = $vehicle->services()->create(['performed_at' => now(), 'shop' => 'AUTOTEC', 'total' => 345]);
        $expense = $vehicle->expenses()->create([
            'category' => 'fuel', 'amount' => 60, 'spent_at' => now(), 'created_by' => $user->id,
        ]);
        $reminder = $vehicle->reminders()->create(['label' => 'Brake fluid', 'due_km' => 90_000]);

        $this->actingAs($user)->get(route('vehicles.show', $vehicle))->assertOk()
            ->assertSee('AUTOTEC')
            ->assertSee(route('vehicles.services.update', [$vehicle, $service]), false)
            ->assertSee(route('vehicles.services.destroy', [$vehicle, $service]), false)
            ->assertSee(route('vehicles.expenses.update', [$vehicle, $expense]), false)
            ->assertSee(route('vehicles.expenses.destroy', [$vehicle, $expense]), false)
            ->assertSee(route('vehicles.reminders.update', [$vehicle, $reminder]), false);
    }
}
