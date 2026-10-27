<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Bill;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard's "needs attention" queue must agree with the bills list.
 *
 * It used to filter on isOverdue(), which only compares dates: a paid bill
 * keeps its past due date, so the queue showed bills the list had already
 * marked green — and the red overdue badge counted them too.
 */
class DashboardAttentionTest extends TestCase
{
    use RefreshDatabase;

    private function bill(User $user, array $attrs = []): Bill
    {
        return Bill::create(array_merge([
            'name'          => 'ΞΕΝΟΔΟΧΕΙΟ ΔΙΑΜΟΝΗ ΚΕΡΚΥΡΑ',
            'category_id'   => Category::create(['name' => 'Travel'])->id,
            'created_by'    => $user->id,
            'amount'        => 586.79,
            'currency_code' => 'EUR',
            'frequency'     => 'once',
            'start_date'    => now()->subMonths(2)->toDateString(),
            'next_due_date' => now()->subDays(8)->toDateString(),
            'is_active'     => true,
        ], $attrs));
    }

    public function test_a_paid_one_off_with_a_past_due_date_is_not_flagged(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR']);
        $account = Account::create([
            'name' => 'Cash', 'opening_balance' => 5000, 'currency_code' => 'EUR', 'created_by' => $user->id,
        ]);
        $bill = $this->bill($user);

        $this->actingAs($user)->post(route('bills.pay', $bill), [
            'payment_mode' => 'full',
            'account_id'   => $account->id,
        ])->assertRedirect();

        $bill->refresh();

        // The list and the dashboard must tell the same story.
        $this->assertSame('paid', $bill->status());
        $this->assertFalse($bill->needsAttention());
        $this->assertTrue($bill->isSettled());
        // isOverdue() is still true — that is exactly why callers must not use it.
        $this->assertTrue($bill->isOverdue());

        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $response->assertViewHas('attention', fn ($attention) => $attention->isEmpty());
        $response->assertViewHas('stats', fn ($stats) => $stats['overdue_count'] === 0);
    }

    public function test_an_unpaid_overdue_bill_is_still_flagged(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR']);
        $bill = $this->bill($user);

        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $response->assertViewHas('attention', fn ($attention) => $attention->contains('id', $bill->id));
        $response->assertViewHas('stats', fn ($stats) => $stats['overdue_count'] === 1);
    }

    public function test_the_sidebar_badge_counts_only_genuinely_overdue_bills(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR', 'locale' => 'en']);
        $account = Account::create([
            'name' => 'Cash', 'opening_balance' => 5000, 'currency_code' => 'EUR', 'created_by' => $user->id,
        ]);

        $unpaid = $this->bill($user);
        $paid   = $this->bill($user, ['name' => 'Settled']);

        $this->actingAs($user)->post(route('bills.pay', $paid), [
            'payment_mode' => 'full',
            'account_id'   => $account->id,
        ])->assertRedirect();

        // The paid one keeps a past due date, so a date-only count would say 2.
        $this->assertSame(1, Bill::overdueCountFor($user->fresh()));

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('aria-label="1 overdue bill"', false);
    }

    public function test_the_badge_is_hidden_when_nothing_is_overdue(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR', 'locale' => 'en']);
        $this->bill($user, ['next_due_date' => now()->addMonth()->toDateString()]);

        $this->assertSame(0, Bill::overdueCountFor($user));

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            // Anchored on the badge's own label: "overdue bill" also appears
            // in the FAB's "Pick an overdue bill".
            ->assertDontSee('aria-label="1 overdue bill"', false);
    }

    public function test_another_users_overdue_bills_are_not_counted(): void
    {
        $mine = User::factory()->create(['currency_code' => 'EUR']);
        $theirs = User::factory()->create(['currency_code' => 'EUR']);
        $this->bill($theirs);

        $this->assertSame(0, Bill::overdueCountFor($mine));
    }

    public function test_a_part_paid_bill_is_counted_at_its_remaining_balance(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR']);
        $account = Account::create([
            'name' => 'Cash', 'opening_balance' => 5000, 'currency_code' => 'EUR', 'created_by' => $user->id,
        ]);
        $bill = $this->bill($user, ['amount' => 100, 'next_due_date' => now()->toDateString()]);

        $this->actingAs($user)->post(route('bills.pay', $bill), [
            'payment_mode'   => 'partial',
            'partial_amount' => '40',
            'account_id'     => $account->id,
        ])->assertRedirect();

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            // 40 paid + 60 still owed = a load of 100, not 140.
            ->assertViewHas('stats', function ($stats) {
                return $stats['month_outstanding'] === 60.0
                    && $stats['month_paid_pct'] === 40;
            });

        $this->assertTrue($bill->fresh()->needsAttention());
    }

    public function test_the_hero_shows_this_month_and_agrees_with_its_progress_bar(): void
    {
        \Carbon\Carbon::setTestNow('2026-10-10 12:00');

        $user = User::factory()->create(['currency_code' => 'EUR']);
        // Due this month, and a yearly bill not due until spring: the old hero
        // counted a twelfth of the yearly one as this month's spending.
        $this->bill($user, ['amount' => 100, 'frequency' => 'monthly', 'start_date' => '2026-01-20', 'next_due_date' => '2026-10-20']);
        $this->bill($user, ['amount' => 1200, 'frequency' => 'yearly', 'start_date' => '2026-03-01', 'next_due_date' => '2027-03-01']);
        // Weekly: due on the 12th, 19th and 26th of October.
        $this->bill($user, ['amount' => 10, 'frequency' => 'weekly', 'start_date' => '2026-10-05', 'next_due_date' => '2026-10-12']);

        \App\Models\Income::create([
            'name' => 'Μισθός', 'amount' => 2000, 'currency_code' => 'EUR', 'frequency' => 'monthly',
            'start_date' => '2026-01-01', 'next_date' => '2026-10-01', 'is_active' => true, 'created_by' => $user->id,
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertViewHas('stats', function ($stats) {
                return $stats['month_expenses'] === 130.0
                    && $stats['month_outstanding'] === 130.0
                    && $stats['month_income'] === 2000.0
                    && $stats['month_net'] === 1870.0;
            });

        \Carbon\Carbon::setTestNow();
    }
}
