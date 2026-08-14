<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Income;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A one-off income records money that already arrived, so creating it settles
 * it. Recurring sources describe what is expected and still need confirming.
 */
class OneOffIncomeTest extends TestCase
{
    use RefreshDatabase;

    private function account(User $user): Account
    {
        return Account::create([
            'name'            => 'Κουμπαράς Νικόλα',
            'opening_balance' => 0,
            'currency_code'   => 'EUR',
            'created_by'      => $user->id,
        ]);
    }

    private function submit(User $user, array $overrides = [])
    {
        return $this->actingAs($user)->post(route('income.store'), array_merge([
            'name'       => 'ΑΠΟΤΑΜΙΕΥΣΗ ΝΙΚΟΛΑΣ',
            'amount'     => '600.00',
            'frequency'  => 'once',
            'start_date' => now()->toDateString(),
        ], $overrides));
    }

    public function test_a_one_off_income_lands_in_the_account_on_creation(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR']);
        $account = $this->account($user);

        $this->submit($user, ['account_id' => $account->id])
            ->assertRedirect(route('income.index'));

        $income = Income::firstWhere('name', 'ΑΠΟΤΑΜΙΕΥΣΗ ΝΙΚΟΛΑΣ');

        $this->assertSame(600.00, $account->fresh()->balance());
        $this->assertSame('deposit', $account->transactions()->sole()->type);
        // It also counts as received, so the page's "0 of 1" summary is right.
        $this->assertNotNull($income->last_received_date);
    }

    public function test_a_recurring_income_still_waits_for_confirmation(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR']);
        $account = $this->account($user);

        $this->submit($user, ['account_id' => $account->id, 'frequency' => 'monthly'])
            ->assertRedirect(route('income.index'));

        $this->assertSame(0.0, $account->fresh()->balance());
        $this->assertSame(0, $account->transactions()->count());
        $this->assertNull(Income::firstWhere('name', 'ΑΠΟΤΑΜΙΕΥΣΗ ΝΙΚΟΛΑΣ')->last_received_date);
    }

    public function test_a_one_off_without_an_account_is_still_marked_received(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR']);

        $this->submit($user)->assertRedirect(route('income.index'));

        $this->assertNotNull(Income::firstWhere('name', 'ΑΠΟΤΑΜΙΕΥΣΗ ΝΙΚΟΛΑΣ')->last_received_date);
    }

    public function test_a_future_dated_one_off_is_not_credited_yet(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR']);
        $account = $this->account($user);

        $this->submit($user, [
            'account_id' => $account->id,
            'start_date' => now()->addMonth()->toDateString(),
        ])->assertRedirect(route('income.index'));

        $this->assertSame(0.0, $account->fresh()->balance());
        $this->assertNull(Income::firstWhere('name', 'ΑΠΟΤΑΜΙΕΥΣΗ ΝΙΚΟΛΑΣ')->last_received_date);
    }

    public function test_the_deposit_is_dated_when_the_money_arrived(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR']);
        $account = $this->account($user);
        $arrived = now()->subDays(10);

        $this->submit($user, [
            'account_id' => $account->id,
            'start_date' => $arrived->toDateString(),
        ])->assertRedirect(route('income.index'));

        $this->assertSame(
            $arrived->toDateString(),
            $account->transactions()->sole()->occurred_at->toDateString(),
        );
    }
}
