<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Income;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A recurring income is confirmed by hand once per cycle: it resets to
 * "expected" every month, can be marked a few days early, can't be marked
 * twice, and remembers how late or early the money actually came.
 */
class IncomeReceiptCycleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function salary(User $user, string $next, array $attrs = []): Income
    {
        $account = Account::create([
            'name' => 'Main', 'opening_balance' => 0, 'currency_code' => 'EUR', 'created_by' => $user->id,
        ]);

        return Income::create(array_merge([
            'name' => 'Salary',
            'amount' => 1500,
            'currency_code' => 'EUR',
            'account_id' => $account->id,
            'frequency' => 'monthly',
            'start_date' => '2026-01-01',
            'next_date' => $next,
            'is_active' => true,
            'created_by' => $user->id,
        ], $attrs));
    }

    public function test_a_second_tap_does_not_deposit_the_salary_twice(): void
    {
        Carbon::setTestNow('2026-10-01 10:00');
        $user = User::factory()->create();
        $income = $this->salary($user, '2026-10-01');

        $this->actingAs($user)->post(route('income.receive', $income))->assertRedirect();
        $this->actingAs($user)->post(route('income.receive', $income->fresh()))->assertStatus(422);

        $this->assertSame(1500.0, $income->account->fresh()->balance());
        $this->assertSame('2026-11-01', $income->fresh()->next_date->toDateString());
    }

    public function test_it_resets_to_expected_when_the_month_turns(): void
    {
        $user = User::factory()->create();
        Carbon::setTestNow('2026-09-01 10:00');
        $income = $this->salary($user, '2026-09-01');
        $this->actingAs($user)->post(route('income.receive', $income))->assertRedirect();
        $this->assertTrue($income->fresh()->isReceivedThisCycle());

        Carbon::setTestNow('2026-10-01 08:00');
        $income = $income->fresh();
        $this->assertFalse($income->isReceivedThisCycle());
        $this->assertTrue($income->canReceiveNow());
        $this->assertFalse($income->receivedForMonth());
    }

    public function test_it_can_be_marked_a_few_days_early_and_counts_for_its_month(): void
    {
        Carbon::setTestNow('2026-09-29 10:00');
        $user = User::factory()->create();
        $income = $this->salary($user, '2026-10-01');

        $this->assertTrue($income->canReceiveNow());
        $this->actingAs($user)->post(route('income.receive', $income))->assertRedirect();

        $income = $income->fresh();
        $this->assertSame(-2, $income->lastReceiptDelay());

        Carbon::setTestNow('2026-10-05 10:00');
        $this->assertTrue($income->isReceivedThisCycle());
        $this->assertTrue($income->receivedForMonth());
    }

    public function test_it_cannot_be_marked_weeks_ahead(): void
    {
        Carbon::setTestNow('2026-10-01 10:00');
        $user = User::factory()->create();
        $income = $this->salary($user, '2026-10-25');

        $this->assertFalse($income->canReceiveNow());
        $this->actingAs($user)->post(route('income.receive', $income))->assertStatus(422);
    }

    public function test_it_reports_how_late_the_money_is_and_was(): void
    {
        Carbon::setTestNow('2026-10-04 10:00');
        $user = User::factory()->create();
        $income = $this->salary($user, '2026-10-01');

        $this->assertSame(3, $income->daysLate());

        $this->actingAs($user)->post(route('income.receive', $income))->assertRedirect();
        $income = $income->fresh();
        $this->assertSame(3, $income->lastReceiptDelay());
        $this->assertSame(0, $income->daysLate());
    }

    public function test_the_list_hides_the_tick_once_received_and_shows_the_delay(): void
    {
        Carbon::setTestNow('2026-10-03 10:00');
        $user = User::factory()->create(['locale' => 'en']);
        $income = $this->salary($user, '2026-10-01');

        $this->actingAs($user)->get(route('income.index'))->assertOk()
            ->assertSee(route('income.receive', $income), false)
            ->assertSee('Late by 2 day(s)');

        $this->actingAs($user)->post(route('income.receive', $income))->assertRedirect();

        $this->actingAs($user)->get(route('income.index'))->assertOk()
            ->assertDontSee(route('income.receive', $income), false)
            ->assertSee('came 2 day(s) late');
    }
}
