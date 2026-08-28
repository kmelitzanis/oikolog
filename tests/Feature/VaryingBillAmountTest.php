<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Bill;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A bill whose cost varies can have this period's figure recorded before it is
 * paid — the point being to see what is owed while there is still time to pay.
 */
class VaryingBillAmountTest extends TestCase
{
    use RefreshDatabase;

    private function bill(User $user, array $attrs = []): Bill
    {
        return Bill::create(array_merge([
            'name'          => 'ΔΕΗ',
            'category_id'   => Category::create(['name' => 'Utilities'])->id,
            'created_by'    => $user->id,
            'amount'        => 0,
            'cost_varies'   => true,
            'currency_code' => 'EUR',
            'frequency'     => 'monthly',
            'start_date'    => now()->subMonths(2)->toDateString(),
            'next_due_date' => now()->addDays(10)->toDateString(),
            'is_active'     => true,
        ], $attrs));
    }

    public function test_the_amount_can_be_recorded_without_paying(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR']);
        $bill = $this->bill($user);

        $this->actingAs($user)
            ->patchJson(route('bills.amount', $bill), ['current_amount' => '108.00'])
            ->assertOk()
            ->assertJson(['current_amount' => 108.0, 'formatted' => 'EUR 108.00']);

        $bill->refresh();
        $this->assertSame(108.0, $bill->periodAmount());
        $this->assertTrue($bill->hasCurrentAmount());
        // Recording is not paying: the bill is still owed, still due in 10 days.
        $this->assertSame('upcoming', $bill->status());
        $this->assertSame(0, $bill->payments()->count());
    }

    public function test_the_recorded_amount_drives_what_is_owed(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR']);
        $bill = $this->bill($user, ['next_due_date' => now()->toDateString()]);

        $this->assertSame(0.0, $bill->getEffectiveRemainingBalance());

        $bill->update(['current_amount' => 108]);
        $bill->refresh();

        $this->assertSame(108.0, $bill->getEffectiveRemainingBalance());
        $this->assertSame(108.0, $bill->monthlyEquivalent());

        // The dashboard's "left to pay" now reflects the real invoice.
        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['month_outstanding'] === 108.0);
    }

    public function test_the_list_shows_the_amount_instead_of_varies(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR', 'locale' => 'en']);
        $bill = $this->bill($user, ['current_amount' => 108]);

        $this->actingAs($user)->get(route('bills.index'))->assertOk()
            ->assertSee('108', false)
            ->assertSee(route('bills.amount', $bill), false);
    }

    public function test_clearing_it_puts_the_bill_back_to_unknown(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR']);
        $bill = $this->bill($user, ['current_amount' => 108]);

        $this->actingAs($user)
            ->patchJson(route('bills.amount', $bill), ['current_amount' => null])
            ->assertOk()
            ->assertJson(['current_amount' => null]);

        $this->assertTrue($bill->fresh()->amountIsUnknown());
    }

    public function test_paying_in_full_clears_it_for_the_next_cycle(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR']);
        $account = Account::create([
            'name' => 'Cash', 'opening_balance' => 500, 'currency_code' => 'EUR', 'created_by' => $user->id,
        ]);
        $bill = $this->bill($user, ['current_amount' => 108]);

        $this->actingAs($user)->post(route('bills.pay', $bill), [
            'payment_mode' => 'full',
            'account_id'   => $account->id,
        ])->assertRedirect();

        $bill->refresh();
        // The payment took the recorded figure, not the zero estimate…
        $this->assertSame('108.00', $bill->payments()->sole()->amount);
        // …and the next period starts unknown again.
        $this->assertNull($bill->current_amount);
        $this->assertTrue($bill->amountIsUnknown());
    }

    public function test_a_fixed_amount_bill_refuses_the_inline_edit(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR']);
        $bill = $this->bill($user, ['cost_varies' => false, 'amount' => 50]);

        $this->actingAs($user)
            ->patchJson(route('bills.amount', $bill), ['current_amount' => '108.00'])
            ->assertStatus(422);

        $this->assertNull($bill->fresh()->current_amount);
    }

    public function test_another_users_bill_is_out_of_reach(): void
    {
        $owner = User::factory()->create(['currency_code' => 'EUR']);
        $other = User::factory()->create(['currency_code' => 'EUR']);
        $bill = $this->bill($owner);

        $this->actingAs($other)
            ->patchJson(route('bills.amount', $bill), ['current_amount' => '108.00'])
            ->assertForbidden();

        $this->assertNull($bill->fresh()->current_amount);
    }
}
