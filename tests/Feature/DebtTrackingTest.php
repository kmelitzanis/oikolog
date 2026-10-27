<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Bill;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Loans and card balances: payments draw the total down, the balance can be
 * changed by hand, and the API pays a bill exactly the way the web page does.
 */
class DebtTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function card(User $user, array $attrs = []): Bill
    {
        $category = Category::create(['name' => 'Cards', 'is_system' => false]);

        return Bill::create(array_merge([
            'name'           => 'Visa',
            'category_id'    => $category->id,
            'created_by'     => $user->id,
            'amount'         => 200.00,
            'currency_code'  => 'EUR',
            'frequency'      => 'monthly',
            'start_date'     => now()->subMonths(2)->toDateString(),
            'next_due_date'  => now()->addDays(5)->toDateString(),
            'is_active'      => true,
            'debt_remaining' => 1000.00,
            'debt_initial'   => 1000.00,
        ], $attrs));
    }

    private function account(User $user): Account
    {
        return Account::create([
            'name' => 'Τρεχούμενος', 'opening_balance' => 1000, 'currency_code' => 'EUR', 'created_by' => $user->id,
        ]);
    }

    public function test_raising_the_balance_keeps_what_was_paid(): void
    {
        $user = User::factory()->create();
        $bill = $this->card($user);

        $this->actingAs($user)->post(route('bills.pay', $bill))->assertRedirect();
        $this->assertSame(800.0, (float) $bill->fresh()->debt_remaining);

        // The limit went up and the card now owes 1,500.
        $this->actingAs($user)
            ->patch(route('bills.debt', $bill), ['debt_remaining' => '1500'])
            ->assertRedirect();

        $bill->refresh();
        $this->assertSame(1500.0, (float) $bill->debt_remaining);
        $this->assertSame(1700.0, (float) $bill->debt_initial);
        $this->assertSame(12, $bill->debtProgress());
    }

    public function test_lowering_the_balance_also_keeps_what_was_paid(): void
    {
        $bill = $this->card(User::factory()->create(), ['debt_remaining' => 600, 'debt_initial' => 1000]);

        $bill->setDebtRemaining(300);

        $this->assertSame(300.0, (float) $bill->debt_remaining);
        $this->assertSame(700.0, (float) $bill->debt_initial);
    }

    public function test_a_balance_on_a_paid_off_card_brings_the_bill_back(): void
    {
        $user = User::factory()->create();
        $bill = $this->card($user, ['debt_remaining' => 0, 'debt_initial' => 1000, 'is_active' => false]);

        $this->actingAs($user)->patch(route('bills.debt', $bill), ['debt_remaining' => '250'])->assertRedirect();

        $bill->refresh();
        $this->assertTrue($bill->is_active);
        $this->assertSame(1250.0, (float) $bill->debt_initial);
    }

    public function test_setting_the_balance_to_zero_retires_the_bill(): void
    {
        $user = User::factory()->create();
        $bill = $this->card($user);

        $this->actingAs($user)->patch(route('bills.debt', $bill), ['debt_remaining' => '0'])->assertRedirect();

        $this->assertFalse($bill->fresh()->is_active);
    }

    public function test_saving_the_edit_form_unchanged_does_not_touch_the_totals(): void
    {
        $user = User::factory()->create();
        $bill = $this->card($user, ['debt_remaining' => 600, 'debt_initial' => 1000]);

        $this->actingAs($user)->put(route('bills.update', $bill), [
            'name'           => $bill->name,
            'category_id'    => $bill->category_id,
            'amount'         => '200',
            'frequency'      => 'monthly',
            'start_date'     => $bill->start_date->toDateString(),
            'debt_remaining' => '600.00',
        ])->assertRedirect();

        $bill->refresh();
        $this->assertSame(600.0, (float) $bill->debt_remaining);
        $this->assertSame(1000.0, (float) $bill->debt_initial);
    }

    public function test_the_bill_page_offers_the_balance_update(): void
    {
        $user = User::factory()->create();
        $bill = $this->card($user);

        $this->actingAs($user)->get(route('bills.show', $bill))
            ->assertOk()
            ->assertSee(route('bills.debt', $bill), false);
    }

    // ── API pays like the web ───────────────────────────────────────────

    public function test_the_api_draws_the_debt_down_and_writes_the_ledger(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $bill = $this->card($user, ['default_account_id' => $account->id]);

        $this->actingAs($user)->postJson("/api/bills/{$bill->id}/pay")
            ->assertOk()
            ->assertJsonPath('status', 'paid');

        $this->assertSame(800.0, (float) $bill->fresh()->debt_remaining);
        $this->assertSame(800.0, $account->fresh()->balance());
    }

    public function test_an_api_amount_short_of_the_due_pays_part_of_it(): void
    {
        $user = User::factory()->create();
        $bill = $this->card($user);

        $this->actingAs($user)->postJson("/api/bills/{$bill->id}/pay", ['amount' => 50])
            ->assertOk()
            ->assertJsonPath('status', 'partial');

        $bill->refresh();
        $this->assertSame(150.0, (float) $bill->remaining_balance);
        $this->assertSame(950.0, (float) $bill->debt_remaining);
    }

    public function test_the_api_refuses_someone_elses_account(): void
    {
        $user = User::factory()->create();
        $this->account($user);
        $stranger = $this->account(User::factory()->create());
        $bill = $this->card($user);

        $this->actingAs($user)->postJson("/api/bills/{$bill->id}/pay", ['account_id' => $stranger->id])
            ->assertUnprocessable();

        $this->assertSame(0, $bill->payments()->count());
    }

    public function test_the_list_shows_the_latest_payment(): void
    {
        $user = User::factory()->create();
        $bill = $this->card($user);

        $this->actingAs($user)->post(route('bills.pay', $bill), ['paid_at' => now()->subDays(3)->toDateString(), 'payment_mode' => 'partial', 'partial_amount' => 20]);
        $this->actingAs($user)->post(route('bills.pay', $bill), ['paid_at' => now()->toDateString()]);

        $this->assertSame(now()->toDateString(), $bill->fresh()->latestPayment->paid_at->toDateString());
        $this->actingAs($user)->get(route('bills.index', ['status' => 'all']))->assertOk();
    }
}
