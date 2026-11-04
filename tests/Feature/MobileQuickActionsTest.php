<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Category;
use App\Models\Income;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The mobile "+" sheet acts on what is due, and the tab bar says where it leads. */
class MobileQuickActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sheet_lists_what_is_due_and_nothing_else(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR', 'locale' => 'el']);
        $category = Category::create(['name' => 'Λογαριασμοί']);
        foreach ([['NOVA', now()->subDays(2)], ['ΔΕΗ', now()->addDays(3)], ['Ασφάλεια', now()->addMonths(2)]] as [$name, $due]) {
            Bill::create([
                'name' => $name, 'category_id' => $category->id, 'created_by' => $user->id, 'amount' => 40,
                'currency_code' => 'EUR', 'frequency' => 'monthly', 'start_date' => $due->copy()->subMonths(3),
                'next_due_date' => $due, 'is_active' => true,
            ]);
        }
        Income::create([
            'name' => 'Μισθός', 'amount' => 2001, 'currency_code' => 'EUR', 'frequency' => 'monthly',
            'start_date' => now()->subMonths(3), 'next_date' => now()->subDay(), 'is_active' => true, 'created_by' => $user->id,
        ]);
        $car = Vehicle::create(['name' => 'Toyota Yaris', 'type' => 'car', 'odometer_km' => 1000, 'created_by' => $user->id]);

        // The composer feeds the layout, not the page, so it is asked directly.
        $this->actingAs($user);
        $view = view('partials.toast');
        app(\App\View\Composers\QuickActionsComposer::class)->compose($view);
        $qa = $view->getData()['quickActions'];
        $this->assertSame(['NOVA', 'ΔΕΗ'], $qa['bills']->pluck('name')->all(), 'Overdue and due this week; not in two months.');
        $this->assertSame(['Μισθός'], $qa['incomes']->pluck('name')->all());

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('expenses.create'), false)
            ->assertSee(route('income.receive', Income::first()), false)
            ->assertSee(route('vehicles.show', ['vehicle' => $car, 'add' => 'fuel']), false)
            ->assertSee(__('messages.nav_home'));
    }

    public function test_coming_from_the_sheet_opens_the_fuel_form(): void
    {
        $user = User::factory()->create();
        $car = Vehicle::create(['name' => 'Toyota Yaris', 'type' => 'car', 'odometer_km' => 1000, 'created_by' => $user->id]);

        $this->actingAs($user)->get(route('vehicles.show', ['vehicle' => $car, 'add' => 'fuel']))
            ->assertOk()
            ->assertSee("adding: 'fuel'", false);

        $this->actingAs($user)->get(route('vehicles.show', $car))
            ->assertOk()
            ->assertSee('adding: null', false);
    }
}
