<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Support\Obd\DtcDictionary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * OBD-II scans: what a Bluetooth scan (JSON) or a hand-typed entry stores,
 * what it refuses, and how codes are explained.
 */
class VehicleScanTest extends TestCase
{
    use RefreshDatabase;

    private function car(User $user, array $attrs = []): Vehicle
    {
        return Vehicle::create(array_merge([
            'name' => 'Volvo V40', 'type' => 'car', 'brand' => 'Volvo', 'odometer_km' => 149_000, 'created_by' => $user->id,
        ], $attrs));
    }

    public function test_a_bluetooth_scan_is_stored_and_filtered(): void
    {
        $user = User::factory()->create();
        $car = $this->car($user);

        $this->actingAs($user)->postJson(route('vehicles.scans.store', $car), [
            'source'      => 'bluetooth',
            'mil_on'      => true,
            'vin'         => 'yv1mz7651g2123456',
            'odometer_km' => 149_375,
            'codes'       => [
                ['code' => 'p0420', 'status' => 'pending'],
                ['code' => 'P0420', 'status' => 'stored'],   // same code, more serious status wins
                ['code' => 'P0171', 'status' => 'stored'],
            ],
            'readings'    => ['battery_v' => 12.61, 'coolant_c' => 88, 'evil' => 1],
            'adapter'     => 'OBDII',
        ])->assertCreated()->assertJson(['codes' => 2]);

        $scan = $car->scans()->first();
        $this->assertSame([['code' => 'P0420', 'status' => 'stored'], ['code' => 'P0171', 'status' => 'stored']], $scan->codes);
        $this->assertEquals(['battery_v' => 12.61, 'coolant_c' => 88], $scan->readings);
        $this->assertSame('YV1MZ7651G2123456', $scan->vin);
        $this->assertTrue($scan->mil_on);
        // The scan's odometer is a fresher reading.
        $this->assertSame(149_375, $car->fresh()->odometer_km);
    }

    public function test_a_manual_entry_splits_the_typed_codes(): void
    {
        $user = User::factory()->create();
        $car = $this->car($user);

        $this->actingAs($user)->post(route('vehicles.scans.store', $car), [
            'codes_text' => 'p0300, P0301;P0420',
            'note'       => 'Από το συνεργείο',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $scan = $car->scans()->first();
        $this->assertSame('manual', $scan->source);
        $this->assertSame(['P0300', 'P0301', 'P0420'], array_column($scan->codes, 'code'));
    }

    public function test_an_invalid_code_is_refused(): void
    {
        $user = User::factory()->create();
        $car = $this->car($user);

        $this->actingAs($user)->post(route('vehicles.scans.store', $car), ['codes_text' => 'P0420 HELLO'])
            ->assertSessionHasErrors('codes.1.code');
        $this->assertSame(0, $car->scans()->count());
    }

    public function test_another_households_car_cannot_be_scanned_into(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $car = $this->car($owner);

        $this->actingAs($stranger)->postJson(route('vehicles.scans.store', $car), ['codes' => []])->assertForbidden();
    }

    public function test_a_scan_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $car = $this->car($user);
        $scan = $car->scans()->create(['scanned_at' => now(), 'codes' => [['code' => 'P0420', 'status' => 'stored']]]);

        $this->actingAs($user)->delete(route('vehicles.scans.destroy', [$car, $scan]))->assertRedirect();
        $this->assertNull($scan->fresh());
    }

    public function test_the_dictionary_explains_generic_codes_and_flags_manufacturer_ones(): void
    {
        app()->setLocale('en');

        $p0420 = DtcDictionary::describe('P0420');
        $this->assertStringContainsString('Catalyst', $p0420['description']);
        $this->assertFalse($p0420['manufacturer']);

        // Unknown but generic: no description, but the system is decoded.
        $p0399 = DtcDictionary::describe('P0399');
        $this->assertNull($p0399['description']);
        $this->assertSame('Ignition system or misfire', $p0399['system']);
        $this->assertSame('high', $p0399['severity']);

        $this->assertTrue(DtcDictionary::describe('P1234')['manufacturer']);
        $this->assertSame('Network communication', DtcDictionary::describe('U0100')['area']);

        $this->assertTrue(DtcDictionary::isValid('b1a2f'));
        $this->assertFalse(DtcDictionary::isValid('P042'));
        $this->assertFalse(DtcDictionary::isValid('X0420'));
    }

    public function test_the_card_shows_on_cars_with_the_latest_scan(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        $car = $this->car($user);
        $car->scans()->create(['scanned_at' => now(), 'mil_on' => true,
            'codes' => [['code' => 'P0420', 'status' => 'stored']], 'readings' => ['battery_v' => 12.4]]);

        $this->actingAs($user)->get(route('vehicles.show', $car))->assertOk()
            ->assertSee('OBD diagnostics')
            ->assertSee('P0420')
            ->assertSee('Catalyst system efficiency below threshold')
            ->assertSee('Check-engine light on')
            ->assertSee('12,4 V');

        $bike = $this->car($user, ['name' => 'Vespa', 'type' => 'motorcycle']);
        $this->actingAs($user)->get(route('vehicles.show', $bike))->assertOk()->assertDontSee('OBD diagnostics');
    }
}
