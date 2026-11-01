<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Expense;
use App\Models\Family;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Everyday purchases: their own page, and a withdrawal on the account they
 * were paid from that stays in step with them.
 */
class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    private function account(User $user, float $opening = 500): Account
    {
        return Account::create([
            'name' => 'Τρεχούμενος', 'opening_balance' => $opening, 'currency_code' => 'EUR', 'created_by' => $user->id,
        ]);
    }

    private function buy(User $user, array $attrs = [])
    {
        return $this->actingAs($user)->post(route('expenses.store'), array_merge([
            'name' => 'Σκλαβενίτης', 'category' => 'groceries', 'amount' => '62.40',
            'spent_at' => now()->toDateString(),
        ], $attrs));
    }

    public function test_a_purchase_takes_the_money_out_of_its_account(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR']);
        $account = $this->account($user);

        $this->buy($user, ['account_id' => $account->id])->assertRedirect()->assertSessionHasNoErrors();

        $expense = Expense::first();
        $this->assertSame('62.40', $expense->amount);
        $this->assertSame(437.6, $account->fresh()->balance());
        $this->assertSame($expense->id, $account->transactions()->first()->expense_id);
    }

    public function test_once_there_are_accounts_one_has_to_be_picked(): void
    {
        $user = User::factory()->create();
        $this->account($user);

        $this->buy($user)->assertSessionHasErrors('account_id');
        $this->assertSame(0, Expense::count());
    }

    public function test_without_accounts_a_purchase_is_still_recorded(): void
    {
        $user = User::factory()->create();

        $this->buy($user)->assertSessionHasNoErrors();
        $this->assertSame(1, Expense::count());
    }

    public function test_someone_elses_account_is_refused(): void
    {
        $user = User::factory()->create();
        $this->account($user);
        $theirs = $this->account(User::factory()->create());

        $this->buy($user, ['account_id' => $theirs->id])->assertSessionHasErrors('account_id');
        $this->assertSame(500.0, $theirs->fresh()->balance());
    }

    public function test_editing_moves_the_withdrawal_with_it(): void
    {
        $user = User::factory()->create();
        $first = $this->account($user);
        $second = $this->account($user, 200);
        $this->buy($user, ['account_id' => $first->id]);
        $expense = Expense::first();

        $this->actingAs($user)->put(route('expenses.update', $expense), [
            'name' => 'Σκλαβενίτης', 'category' => 'groceries', 'amount' => '80',
            'spent_at' => now()->toDateString(), 'account_id' => $second->id,
        ])->assertRedirect();

        $this->assertSame(500.0, $first->fresh()->balance());
        $this->assertSame(120.0, $second->fresh()->balance());
    }

    public function test_deleting_a_purchase_puts_the_money_back(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $this->buy($user, ['account_id' => $account->id]);

        $this->actingAs($user)->delete(route('expenses.destroy', Expense::first()))->assertRedirect();

        $this->assertSame(500.0, $account->fresh()->balance());
        $this->assertSame(0, $account->transactions()->count());
    }

    public function test_its_withdrawal_cannot_be_deleted_from_the_account(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $this->buy($user, ['account_id' => $account->id]);

        $this->actingAs($user)
            ->delete(route('accounts.movements.destroy', [$account, $account->transactions()->first()]))
            ->assertStatus(422);
    }

    public function test_the_page_shows_one_month_at_a_time(): void
    {
        $user = User::factory()->create();
        $this->buy($user, ['name' => 'Δώρο Κατερίνας', 'category' => 'gifts']);
        $this->buy($user, ['name' => 'Παλιά αγορά', 'spent_at' => now()->subMonthNoOverflow()->toDateString()]);

        $this->actingAs($user)->get(route('expenses.index'))
            ->assertOk()
            ->assertSee('Δώρο Κατερίνας')
            ->assertDontSee('Παλιά αγορά');

        $this->actingAs($user)->get(route('expenses.index', ['month' => now()->subMonthNoOverflow()->format('Y-m')]))
            ->assertOk()
            ->assertSee('Παλιά αγορά');
    }

    public function test_purchases_stay_private_unless_shared(): void
    {
        $me = User::factory()->create();
        $family = Family::create(['name' => 'Σπίτι', 'owner_id' => $me->id]);
        $me->update(['family_id' => $family->id]);
        $partner = User::factory()->create(['family_id' => $family->id]);

        $this->buy($me, ['name' => 'Ιδιωτικό']);
        $this->buy($me, ['name' => 'Κοινό', 'is_shared' => '1']);

        $this->actingAs($partner)->get(route('expenses.index'))
            ->assertSee('Κοινό')
            ->assertDontSee('Ιδιωτικό');
        $this->actingAs($partner)->get(route('expenses.edit', Expense::firstWhere('name', 'Ιδιωτικό')))->assertForbidden();
    }

    public function test_a_future_date_is_refused(): void
    {
        $this->buy(User::factory()->create(), ['spent_at' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors('spent_at');
    }
}
