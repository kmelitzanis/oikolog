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

    // ── Editing keeps the ledger in step ────────────────────────────────

    private function edit(User $user, Income $income, array $overrides = [])
    {
        return $this->actingAs($user)->put(route('income.update', $income), array_merge([
            'name'       => $income->name,
            'amount'     => $income->amount,
            'account_id' => $income->account_id,
            'frequency'  => $income->frequency,
            'start_date' => $income->start_date->toDateString(),
        ], $overrides));
    }

    public function test_changing_the_amount_moves_the_deposit_too(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR']);
        $account = $this->account($user);

        $this->submit($user, ['account_id' => $account->id]);
        $income = Income::firstWhere('name', 'ΑΠΟΤΑΜΙΕΥΣΗ ΝΙΚΟΛΑΣ');

        $this->assertSame(600.00, $account->fresh()->balance());

        $this->edit($user, $income, ['amount' => '650.00'])->assertRedirect();

        // The balance follows the edit instead of keeping the original figure.
        $this->assertSame(650.00, $account->fresh()->balance());
        $this->assertSame(1, $account->transactions()->count());
    }

    public function test_renaming_relabels_the_movement(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR']);
        $account = $this->account($user);

        $this->submit($user, ['account_id' => $account->id]);
        $income = Income::firstWhere('name', 'ΑΠΟΤΑΜΙΕΥΣΗ ΝΙΚΟΛΑΣ');

        $this->edit($user, $income, ['name' => 'ΔΩΡΟ ΑΠΟ ΓΙΩΡΓΟ'])->assertRedirect();

        $this->assertSame('ΔΩΡΟ ΑΠΟ ΓΙΩΡΓΟ', $account->transactions()->sole()->description);
    }

    public function test_switching_account_moves_the_money_across(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR']);
        $from = $this->account($user);
        $to = Account::create([
            'name' => 'Μετρητά', 'opening_balance' => 0, 'currency_code' => 'EUR', 'created_by' => $user->id,
        ]);

        $this->submit($user, ['account_id' => $from->id]);
        $income = Income::firstWhere('name', 'ΑΠΟΤΑΜΙΕΥΣΗ ΝΙΚΟΛΑΣ');

        $this->edit($user, $income, ['account_id' => $to->id])->assertRedirect();

        $this->assertSame(0.0, $from->fresh()->balance());
        $this->assertSame(600.00, $to->fresh()->balance());
    }

    public function test_clearing_the_account_withdraws_the_deposit(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR']);
        $account = $this->account($user);

        $this->submit($user, ['account_id' => $account->id]);
        $income = Income::firstWhere('name', 'ΑΠΟΤΑΜΙΕΥΣΗ ΝΙΚΟΛΑΣ');

        $this->edit($user, $income, ['account_id' => ''])->assertRedirect();

        $this->assertSame(0.0, $account->fresh()->balance());
        $this->assertSame(0, $account->transactions()->count());
    }

    public function test_pushing_the_date_into_the_future_takes_the_money_back(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR']);
        $account = $this->account($user);

        $this->submit($user, ['account_id' => $account->id]);
        $income = Income::firstWhere('name', 'ΑΠΟΤΑΜΙΕΥΣΗ ΝΙΚΟΛΑΣ');

        $this->edit($user, $income, ['start_date' => now()->addMonth()->toDateString()])->assertRedirect();

        $this->assertSame(0.0, $account->fresh()->balance());
        $this->assertNull($income->fresh()->last_received_date);

        // …and bringing it back credits it again.
        $this->edit($user, $income->fresh(), ['start_date' => now()->toDateString()])->assertRedirect();

        $this->assertSame(600.00, $account->fresh()->balance());
    }

    public function test_a_recurring_sources_confirmed_receipts_are_never_rewritten(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR']);
        $account = $this->account($user);

        $this->submit($user, ['account_id' => $account->id, 'frequency' => 'monthly']);
        $income = Income::firstWhere('name', 'ΑΠΟΤΑΜΙΕΥΣΗ ΝΙΚΟΛΑΣ');

        $this->actingAs($user)->post(route('income.receive', $income))->assertRedirect();
        $this->assertSame(600.00, $account->fresh()->balance());

        // Editing the schedule must not touch what was already banked.
        $this->edit($user, $income->fresh(), ['amount' => '900.00'])->assertRedirect();

        $this->assertSame(600.00, $account->fresh()->balance());
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
