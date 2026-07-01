<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillSeriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_series_endpoint_returns_monthly_data()
    {
        $user = User::factory()->create(['currency_code' => 'EUR']);

        // create a category
        $cat = Category::create(['name' => 'General', 'is_system' => false]);

        // Create two bills: one expense and one income (negative amount)
        Bill::create([
            'name' => 'Subscription',
            'category_id' => $cat->id,
            'created_by' => $user->id,
            'amount' => 20.00,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'frequency_interval' => 1,
            'start_date' => now()->subMonths(3)->toDateString(),
            'next_due_date' => now()->toDateString(),
            'is_active' => true,
        ]);

        Bill::create([
            'name' => 'Salary',
            'category_id' => $cat->id,
            'created_by' => $user->id,
            'amount' => -1500.00,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'frequency_interval' => 1,
            'start_date' => now()->subMonths(6)->toDateString(),
            'next_due_date' => now()->toDateString(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->getJson('/api/bills/series');
        $response->assertStatus(200);
        $response->assertJsonStructure(['months', 'spending', 'income', 'currency_code']);

        $data = $response->json();
        $this->assertCount(12, $data['months']);
        $this->assertCount(12, $data['spending']);
        $this->assertCount(12, $data['income']);
        $this->assertEquals('EUR', $data['currency_code']);
    }

    public function test_changing_frequency_realigns_next_due_date_onto_new_schedule()
    {
        $user = User::factory()->create(['currency_code' => 'EUR']);
        $cat  = Category::create(['name' => 'Insurance', 'is_system' => false]);

        // A monthly bill that has drifted one month past its start date.
        $bill = Bill::create([
            'name'          => 'Car Insurance',
            'category_id'   => $cat->id,
            'created_by'    => $user->id,
            'amount'        => 62.00,
            'currency_code' => 'EUR',
            'frequency'     => 'monthly',
            'start_date'    => '2026-05-15',
            'next_due_date' => '2026-06-15',
            'is_active'     => true,
        ]);

        // Switch it to quarterly via the web update endpoint.
        $this->actingAs($user)->put(route('bills.update', $bill), [
            'name'        => 'Car Insurance',
            'category_id' => $cat->id,
            'amount'      => 62.00,
            'frequency'   => 'quarterly',
            'start_date'  => '2026-05-15',
        ])->assertRedirect();

        // next_due_date must land on the quarterly schedule (15 May), not the
        // stale monthly cadence (15 Jun).
        $this->assertEquals('2026-05-15', $bill->fresh()->next_due_date->toDateString());
    }
}

