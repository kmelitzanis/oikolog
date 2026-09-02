<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Bill;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillsDefaultFilterTest extends TestCase
{
    use RefreshDatabase;

    private function bill(User $user, string $name, string $due): Bill
    {
        return Bill::create([
            'name'          => $name,
            // A neutral category name: the filter dropdown lists every
            // category, so naming it after the bill makes assertDontSee lie.
            'category_id'   => Category::firstOrCreate(['name' => 'Utilities'])->id,
            'created_by'    => $user->id,
            'amount'        => 10,
            'currency_code' => 'EUR',
            'frequency'     => 'monthly',
            'start_date'    => now()->subMonths(6)->toDateString(),
            'next_due_date' => $due,
            'is_active'     => true,
        ]);
    }

    public function test_the_list_opens_on_this_month(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR', 'locale' => 'en']);
        $this->bill($user, 'DueNow', now()->toDateString());
        $this->bill($user, 'DueLater', now()->addMonths(3)->toDateString());

        $this->actingAs($user)->get(route('bills.index'))->assertOk()
            ->assertViewHas('status', 'this_month')
            ->assertSee('DueNow')
            ->assertDontSee('DueLater');
    }

    public function test_all_is_still_reachable(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR', 'locale' => 'en']);
        $this->bill($user, 'DueNow', now()->toDateString());
        $this->bill($user, 'DueLater', now()->addMonths(3)->toDateString());

        $this->actingAs($user)->get(route('bills.index', ['status' => 'all']))->assertOk()
            ->assertViewHas('status', 'all')
            ->assertSee('DueNow')
            ->assertSee('DueLater');
    }

    public function test_the_chosen_cut_survives_a_search(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR', 'locale' => 'en']);
        $this->bill($user, 'DueLater', now()->addMonths(3)->toDateString());

        $this->actingAs($user)
            ->get(route('bills.index', ['status' => 'all', 'search' => 'Due']))
            ->assertOk()
            ->assertViewHas('status', 'all')
            ->assertSee('DueLater');
    }

    /** The default account is preselected when the bill names none of its own. */
    public function test_the_pay_modal_starts_on_the_users_default_account(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR', 'locale' => 'en']);

        $first = Account::create([
            'name' => 'AAA First', 'opening_balance' => 0, 'currency_code' => 'EUR', 'created_by' => $user->id,
        ]);
        $chosen = Account::create([
            'name' => 'ZZZ Chosen', 'opening_balance' => 0, 'currency_code' => 'EUR', 'created_by' => $user->id,
        ]);

        // Without a preference the modal falls back to whichever sorts first.
        $this->actingAs($user)->get(route('bills.index'))->assertOk()
            ->assertSee("data.defaultAccountId || '{$first->id}'", false);

        $user->update(['default_account_id' => $chosen->id]);

        $this->actingAs($user)->get(route('bills.index'))->assertOk()
            ->assertSee("data.defaultAccountId || '{$chosen->id}'", false);
    }

    public function test_the_default_account_can_be_set_from_settings(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR']);
        $account = Account::create([
            'name' => 'Cash', 'opening_balance' => 0, 'currency_code' => 'EUR', 'created_by' => $user->id,
        ]);

        $this->actingAs($user)->post(route('settings.update'), [
            'name'               => $user->name,
            'email'              => $user->email,
            'currency_code'      => 'EUR',
            'default_account_id' => $account->id,
        ])->assertRedirect();

        $this->assertSame($account->id, $user->fresh()->default_account_id);

        // Blank clears it back to "no preference".
        $this->actingAs($user)->post(route('settings.update'), [
            'name'               => $user->name,
            'email'              => $user->email,
            'currency_code'      => 'EUR',
            'default_account_id' => '',
        ])->assertRedirect();

        $this->assertNull($user->fresh()->default_account_id);
    }
}
