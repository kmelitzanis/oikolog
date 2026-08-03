<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Income;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Accounts are where money actually sits. Receiving an income deposits into
 * one, paying a bill withdraws from one, and a transfer moves between two —
 * and the balance is always the opening balance plus that history.
 */
class AccountLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function makeAccount(User $user, array $attrs = []): Account
    {
        return Account::create(array_merge([
            'name' => 'Savings',
            'opening_balance' => 1000.00,
            'currency_code' => 'EUR',
            'created_by' => $user->id,
        ], $attrs));
    }

    private function makeBill(User $user, array $attrs = []): Bill
    {
        $category = Category::create(['name' => 'Housing', 'is_system' => false]);

        return Bill::create(array_merge([
            'name' => 'Rent',
            'category_id' => $category->id,
            'created_by' => $user->id,
            'amount' => 450.00,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => now()->subMonths(3)->toDateString(),
            'next_due_date' => now()->addDays(10)->toDateString(),
            'is_active' => true,
        ], $attrs));
    }

    public function test_balance_is_the_opening_balance_when_nothing_moved(): void
    {
        $account = $this->makeAccount(User::factory()->create());

        $this->assertSame(1000.00, $account->balance());
    }

    public function test_paying_a_bill_withdraws_from_the_chosen_account(): void
    {
        $user = User::factory()->create();
        $account = $this->makeAccount($user);
        $bill = $this->makeBill($user);

        $this->actingAs($user)
            ->post(route('bills.pay', $bill), ['account_id' => $account->id])
            ->assertRedirect();

        $this->assertSame(550.00, $account->fresh()->balance());

        $movement = $account->transactions()->first();
        $this->assertSame('withdrawal', $movement->type);
        $this->assertSame(-1, $movement->direction);
        $this->assertNotNull($movement->payment_id);
    }

    public function test_a_payment_cannot_skip_the_account_once_one_exists(): void
    {
        $user = User::factory()->create();
        $this->makeAccount($user);
        $bill = $this->makeBill($user);

        $this->actingAs($user)
            ->post(route('bills.pay', $bill))
            ->assertSessionHasErrors('account_id');

        $this->assertSame(0, $bill->payments()->count());
    }

    public function test_undoing_a_payment_puts_the_money_back(): void
    {
        $user = User::factory()->create();
        $account = $this->makeAccount($user);
        $bill = $this->makeBill($user);

        $this->actingAs($user)->post(route('bills.pay', $bill), ['account_id' => $account->id]);
        $this->assertSame(550.00, $account->fresh()->balance());

        $this->actingAs($user)->delete(route('bills.unpay', $bill))->assertRedirect();

        $this->assertSame(1000.00, $account->fresh()->balance());
        $this->assertSame(0, $account->transactions()->count());
    }

    public function test_receiving_an_income_deposits_into_its_account(): void
    {
        $user = User::factory()->create();
        $account = $this->makeAccount($user, ['opening_balance' => 0]);

        $income = Income::create([
            'name' => 'Salary',
            'amount' => 1200.00,
            'currency_code' => 'EUR',
            'account_id' => $account->id,
            'frequency' => 'monthly',
            'start_date' => now()->subMonth()->toDateString(),
            'next_date' => now()->toDateString(),
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->post(route('income.receive', $income))->assertRedirect();

        $this->assertSame(1200.00, $account->fresh()->balance());
        $this->assertSame('deposit', $account->transactions()->first()->type);
    }

    public function test_a_transfer_moves_money_between_two_accounts(): void
    {
        $user = User::factory()->create();
        $salary = $this->makeAccount($user, ['name' => 'Salary', 'opening_balance' => 800]);
        $savings = $this->makeAccount($user, ['name' => 'Savings', 'opening_balance' => 200]);

        $this->actingAs($user)
            ->post(route('accounts.transfer', $salary), [
                'to_account_id' => $savings->id,
                'amount' => 588,
            ])
            ->assertRedirect();

        $this->assertSame(212.00, $salary->fresh()->balance());
        $this->assertSame(788.00, $savings->fresh()->balance());

        // Both halves share one group, which is what makes it one event.
        $groups = AccountTransaction::pluck('transfer_group')->unique();
        $this->assertCount(1, $groups);
        $this->assertNotNull($groups->first());
    }

    public function test_deleting_one_leg_of_a_transfer_removes_both(): void
    {
        $user = User::factory()->create();
        $from = $this->makeAccount($user, ['name' => 'A']);
        $to = $this->makeAccount($user, ['name' => 'B']);

        $this->actingAs($user)->post(route('accounts.transfer', $from), [
            'to_account_id' => $to->id,
            'amount' => 100,
        ]);

        $leg = $from->transactions()->first();
        $this->actingAs($user)
            ->delete(route('accounts.movements.destroy', [$from, $leg]))
            ->assertRedirect();

        $this->assertSame(0, AccountTransaction::count());
        $this->assertSame(1000.00, $to->fresh()->balance());
    }

    public function test_a_payment_movement_cannot_be_deleted_from_the_ledger(): void
    {
        $user = User::factory()->create();
        $account = $this->makeAccount($user);
        $bill = $this->makeBill($user);

        $this->actingAs($user)->post(route('bills.pay', $bill), ['account_id' => $account->id]);
        $movement = $account->transactions()->first();

        $this->actingAs($user)
            ->delete(route('accounts.movements.destroy', [$account, $movement]))
            ->assertStatus(422);

        $this->assertSame(1, $account->transactions()->count());
    }

    public function test_another_users_account_is_out_of_reach(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $account = $this->makeAccount($owner);

        $this->actingAs($stranger)->get(route('accounts.show', $account))->assertForbidden();
    }

    public function test_a_used_account_is_deactivated_rather_than_deleted(): void
    {
        $user = User::factory()->create();
        $account = $this->makeAccount($user);
        $bill = $this->makeBill($user);
        $this->actingAs($user)->post(route('bills.pay', $bill), ['account_id' => $account->id]);

        $this->actingAs($user)->delete(route('accounts.destroy', $account))->assertRedirect();

        $this->assertNotNull($account->fresh());
        $this->assertFalse($account->fresh()->is_active);
    }

    /** The pay modal, the bill form and the income form all changed shape. */
    public function test_pages_that_pick_an_account_render(): void
    {
        $user = User::factory()->create();
        $account = $this->makeAccount($user);
        $bill = $this->makeBill($user, ['default_account_id' => $account->id]);

        $income = Income::create([
            'name' => 'Salary',
            'amount' => 1200.00,
            'currency_code' => 'EUR',
            'account_id' => $account->id,
            'frequency' => 'monthly',
            'start_date' => now()->subMonth()->toDateString(),
            'next_date' => now()->toDateString(),
            'created_by' => $user->id,
        ]);

        // The dashboard is left out: it builds its chart with a raw MySQL
        // DATE_FORMAT() call, which SQLite has no function for.
        $this->actingAs($user)->get(route('bills.index'))->assertOk()->assertSee('Savings');
        $this->actingAs($user)->get(route('bills.edit', $bill))->assertOk();
        $this->actingAs($user)->get(route('bills.show', $bill))->assertOk();
        $this->actingAs($user)->get(route('income.index'))->assertOk()->assertSee('Savings');
        $this->actingAs($user)->get(route('income.show', $income))->assertOk();
        $this->actingAs($user)->get(route('income.edit', $income))->assertOk();
    }

    public function test_account_pages_render(): void
    {
        $user = User::factory()->create();
        $account = $this->makeAccount($user);

        // Accounts live on the income page now; the old route only redirects.
        $this->actingAs($user)->get(route('accounts.index'))
            ->assertRedirect(route('income.index', ['tab' => 'accounts']));
        $this->actingAs($user)->get(route('income.index', ['tab' => 'accounts']))
            ->assertOk()->assertSee('Savings');
        $this->actingAs($user)->get(route('accounts.show', $account))->assertOk();
        $this->actingAs($user)->get(route('accounts.create'))->assertOk();
        $this->actingAs($user)->get(route('accounts.edit', $account))->assertOk();
    }
}
