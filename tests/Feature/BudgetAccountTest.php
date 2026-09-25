<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Services\Ledger;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A spending ("budget") account is an envelope: a fixed amount per pay cycle,
 * spending comes out of it, it never reads negative, and every payday it
 * starts again from the full amount. Leftovers only move when asked to.
 */
class BudgetAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function envelope(User $user, array $attrs = []): Account
    {
        return Account::create(array_merge([
            'name' => 'Household',
            'kind' => 'budget',
            'cycle_amount' => 1200,
            'cycle_day' => 25,
            'currency_code' => 'EUR',
            'created_by' => $user->id,
        ], $attrs));
    }

    private function savings(User $user): Account
    {
        return Account::create([
            'name' => 'Savings', 'opening_balance' => 5000,
            'currency_code' => 'EUR', 'created_by' => $user->id,
        ]);
    }

    private function spend(Account $a, float $amount, string $when): void
    {
        app(Ledger::class)->withdraw($a, $amount, Carbon::parse($when), $a->created_by, 'spend');
    }

    public function test_the_cycle_runs_from_payday_to_the_day_before_the_next(): void
    {
        $a = $this->envelope(User::factory()->create());

        [$start, $end] = $a->cycleFor(Carbon::parse('2026-09-10 12:00'));
        $this->assertSame('2026-08-25 00:00:00', $start->toDateTimeString());
        $this->assertSame('2026-09-24 23:59:59', $end->toDateTimeString());

        [$start] = $a->cycleFor(Carbon::parse('2026-09-25 08:00'));
        $this->assertSame('2026-09-25', $start->toDateString());
    }

    public function test_a_payday_past_the_end_of_a_short_month_falls_on_its_last_day(): void
    {
        $a = $this->envelope(User::factory()->create(), ['cycle_day' => 31]);

        [$start, $end] = $a->cycleFor(Carbon::parse('2027-03-05'));
        $this->assertSame('2027-02-28', $start->toDateString());
        $this->assertSame('2027-03-30', $end->toDateString());
    }

    public function test_spending_comes_off_the_cycle_and_never_reads_negative(): void
    {
        Carbon::setTestNow('2026-10-05 12:00');
        $a = $this->envelope(User::factory()->create());

        $this->spend($a, 850, '2026-10-01');
        $c = $a->cycleSummary();
        $this->assertSame(350.0, $c['left']);
        $this->assertSame(0.0, $c['over']);

        $this->spend($a, 400, '2026-10-03');
        $c = $a->fresh()->cycleSummary();
        $this->assertSame(0.0, $c['left']);
        $this->assertSame(50.0, $c['over']);
        $this->assertSame(0.0, $a->fresh()->available());
    }

    public function test_a_new_cycle_starts_from_the_full_amount_even_after_overspending(): void
    {
        Carbon::setTestNow('2026-10-20 12:00');
        $a = $this->envelope(User::factory()->create());
        $this->spend($a, 1500, '2026-10-01');

        Carbon::setTestNow('2026-10-26 09:00');
        $c = $a->fresh()->cycleSummary();
        $this->assertSame(1200.0, $c['left']);
        $this->assertSame(0.0, $c['over']);
    }

    public function test_envelopes_are_kept_out_of_the_total_balance(): void
    {
        Carbon::setTestNow('2026-10-05 12:00');
        $user = User::factory()->create();
        $a = $this->envelope($user);
        $this->savings($user);
        $this->spend($a, 200, '2026-10-01');

        $stats = Account::summaryFor($user)['stats'];
        $this->assertSame(5000.0, $stats['total']);
        $this->assertSame(1000.0, $stats['budget_left']);
    }

    public function test_the_leftover_can_be_moved_to_savings_without_touching_the_new_cycle(): void
    {
        $user = User::factory()->create();
        Carbon::setTestNow('2026-08-20 12:00');
        $a = $this->envelope($user);
        $savings = $this->savings($user);

        Carbon::setTestNow('2026-09-01 12:00');
        $this->spend($a, 900, '2026-09-01');

        Carbon::setTestNow('2026-09-26 10:00');
        $leftover = $a->fresh()->pendingLeftover();
        $this->assertSame(300.0, $leftover['left']);

        $this->actingAs($user)
            ->post(route('accounts.settle-cycle', $a), ['to_account_id' => $savings->id, 'amount' => 300])
            ->assertRedirect();

        $this->assertSame(5300.0, $savings->fresh()->balance());
        $this->assertSame(1200.0, $a->fresh()->cycleSummary()['left']);
        $this->assertNull($a->fresh()->pendingLeftover());
    }

    public function test_leaving_the_leftover_moves_nothing_and_stops_asking(): void
    {
        $user = User::factory()->create();
        Carbon::setTestNow('2026-08-20 12:00');
        $a = $this->envelope($user);
        $savings = $this->savings($user);

        Carbon::setTestNow('2026-09-26 10:00');
        $this->assertNotNull($a->fresh()->pendingLeftover());

        $this->actingAs($user)
            ->post(route('accounts.settle-cycle', $a), ['action' => 'keep'])
            ->assertRedirect();

        $this->assertNull($a->fresh()->pendingLeftover());
        $this->assertSame(5000.0, $savings->fresh()->balance());
    }

    public function test_more_than_the_leftover_cannot_be_moved(): void
    {
        $user = User::factory()->create();
        Carbon::setTestNow('2026-08-20 12:00');
        $a = $this->envelope($user);
        $savings = $this->savings($user);
        Carbon::setTestNow('2026-09-01 12:00');
        $this->spend($a, 1000, '2026-09-01');

        Carbon::setTestNow('2026-09-26 10:00');
        $this->actingAs($user)
            ->post(route('accounts.settle-cycle', $a), ['to_account_id' => $savings->id, 'amount' => 500])
            ->assertSessionHasErrors('amount');
    }

    public function test_an_envelope_created_mid_cycle_does_not_ask_about_it(): void
    {
        Carbon::setTestNow('2026-09-10 12:00');
        $a = $this->envelope(User::factory()->create());

        Carbon::setTestNow('2026-09-26 10:00');
        $this->assertNull($a->fresh()->pendingLeftover());
    }

    public function test_the_form_switches_an_account_to_a_budget(): void
    {
        $user = User::factory()->create();
        $account = $this->savings($user);

        $this->actingAs($user)->put(route('accounts.update', $account), [
            'name' => 'Savings', 'kind' => 'budget', 'cycle_amount' => 900, 'cycle_day' => 1, 'is_active' => 1,
        ])->assertRedirect();

        $account->refresh();
        $this->assertTrue($account->isBudget());
        $this->assertSame(1, $account->cycle_day);

        $this->actingAs($user)->put(route('accounts.update', $account), ['name' => 'Savings', 'kind' => 'budget'])
            ->assertSessionHasErrors(['cycle_amount', 'cycle_day']);
    }

    public function test_pages_render_for_a_budget_account(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        Carbon::setTestNow('2026-08-20 12:00');
        $a = $this->envelope($user);
        $this->savings($user);

        Carbon::setTestNow('2026-09-26 10:00');
        $this->actingAs($user)->get(route('accounts.show', $a))->assertOk()
            ->assertSee('Left this cycle')->assertSee('1,200.00 unspent', false);
        $this->actingAs($user)->get(route('income.index'))->assertOk()->assertSee('Household');
        $this->actingAs($user)->get(route('accounts.edit', $a))->assertOk();
    }
}
