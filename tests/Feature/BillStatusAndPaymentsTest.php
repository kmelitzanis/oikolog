<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Category;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the bill status vocabulary and the two distinct ways a payment can be
 * removed: the row-level Undo (latest payment only, rolls the schedule back) and
 * deleting an arbitrary entry from the history (must not touch the schedule).
 */
class BillStatusAndPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private function makeBill(User $user, array $attrs = []): Bill
    {
        $category = Category::create(['name' => 'Utilities', 'is_system' => false]);

        return Bill::create(array_merge([
            'name'          => 'Electricity',
            'category_id'   => $category->id,
            'created_by'    => $user->id,
            'amount'        => 100.00,
            'currency_code' => 'EUR',
            'frequency'     => 'monthly',
            'start_date'    => now()->subMonths(3)->toDateString(),
            'next_due_date' => now()->addDays(20)->toDateString(),
            'is_active'     => true,
        ], $attrs));
    }

    // ── status() ────────────────────────────────────────────────────────

    public function test_recurring_bill_paid_last_cycle_but_due_again_is_not_paid(): void
    {
        // The regression that started all this: the list tinted such a bill green
        // off `last_paid_date` while still offering "Mark as paid".
        $bill = $this->makeBill(User::factory()->create(), [
            'last_paid_date' => now()->subMonth()->toDateString(),
            'next_due_date'  => now()->subDays(3)->toDateString(),
        ]);

        $this->assertFalse($bill->isCurrentCyclePaid());
        $this->assertSame('overdue', $bill->status());
    }

    public function test_recurring_bill_paid_this_cycle_reads_as_paid(): void
    {
        $bill = $this->makeBill(User::factory()->create(), [
            'last_paid_date' => now()->toDateString(),
            'next_due_date'  => now()->addMonth()->toDateString(),
        ]);

        $this->assertSame('paid', $bill->status());
    }

    public function test_partial_balance_outranks_the_due_date(): void
    {
        $bill = $this->makeBill(User::factory()->create(), [
            'last_paid_date'    => now()->toDateString(),
            'remaining_balance' => 40.00,
            'next_due_date'     => now()->subDay()->toDateString(),
        ]);

        $this->assertSame('partial', $bill->status());
    }

    public function test_inactive_bill_reads_as_inactive(): void
    {
        $bill = $this->makeBill(User::factory()->create(), ['is_active' => false]);

        $this->assertSame('inactive', $bill->status());
    }

    public function test_due_within_a_week_reads_as_soon(): void
    {
        $bill = $this->makeBill(User::factory()->create(), [
            'next_due_date' => now()->addDays(3)->toDateString(),
        ]);

        $this->assertSame('soon', $bill->status());
    }

    // ── Removing payments ───────────────────────────────────────────────

    public function test_undo_rolls_the_due_date_back_to_the_settled_cycle(): void
    {
        $user = User::factory()->create();
        $bill = $this->makeBill($user);

        $this->actingAs($user)->post(route('bills.pay', $bill))->assertRedirect();

        $bill->refresh();
        $this->assertSame('paid', $bill->status());

        $this->actingAs($user)->delete(route('bills.unpay', $bill))->assertRedirect();

        $bill->refresh();
        $this->assertSame(0, $bill->payments()->count());
        $this->assertNull($bill->last_paid_date);
        $this->assertSame(now()->toDateString(), $bill->next_due_date->toDateString());
    }

    public function test_deleting_an_older_payment_leaves_the_schedule_alone(): void
    {
        $user = User::factory()->create();
        $bill = $this->makeBill($user, ['next_due_date' => now()->addDays(20)->toDateString()]);

        $old = Payment::create([
            'bill_id'       => $bill->id,
            'paid_by'       => $user->id,
            'amount'        => 100.00,
            'currency_code' => 'EUR',
            'paid_at'       => now()->subMonths(2),
        ]);
        $latest = Payment::create([
            'bill_id'       => $bill->id,
            'paid_by'       => $user->id,
            'amount'        => 100.00,
            'currency_code' => 'EUR',
            'paid_at'       => now()->subDays(2),
        ]);
        $bill->update(['last_paid_date' => $latest->paid_at->toDateString()]);

        $dueBefore = $bill->next_due_date->toDateString();

        $this->actingAs($user)
            ->delete(route('bills.payments.destroy', [$bill, $old]))
            ->assertRedirect();

        $bill->refresh();
        $this->assertSame(1, $bill->payments()->count());
        $this->assertSame($dueBefore, $bill->next_due_date->toDateString(), 'An old correction must not move the schedule.');
        $this->assertSame($latest->paid_at->toDateString(), $bill->last_paid_date->toDateString());
    }

    public function test_deleting_the_latest_payment_from_history_rolls_the_schedule_back(): void
    {
        $user = User::factory()->create();
        $bill = $this->makeBill($user);

        $this->actingAs($user)->post(route('bills.pay', $bill))->assertRedirect();
        $bill->refresh();
        $payment = $bill->payments()->latest('paid_at')->first();

        $this->actingAs($user)
            ->delete(route('bills.payments.destroy', [$bill, $payment]))
            ->assertRedirect();

        $bill->refresh();
        $this->assertSame(now()->toDateString(), $bill->next_due_date->toDateString());
    }

    public function test_a_payment_cannot_be_deleted_through_another_bill(): void
    {
        $user  = User::factory()->create();
        $billA = $this->makeBill($user);
        $billB = $this->makeBill($user, ['name' => 'Water']);

        $payment = Payment::create([
            'bill_id'       => $billA->id,
            'paid_by'       => $user->id,
            'amount'        => 50.00,
            'currency_code' => 'EUR',
            'paid_at'       => now(),
        ]);

        $this->actingAs($user)
            ->delete(route('bills.payments.destroy', [$billB, $payment]))
            ->assertNotFound();

        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
    }

    // ── The redesigned pages actually render ────────────────────────────

    public function test_bill_pages_render(): void
    {
        $user = User::factory()->create();
        $bill = $this->makeBill($user);

        Payment::create([
            'bill_id'       => $bill->id,
            'paid_by'       => $user->id,
            'amount'        => 60.00,
            'is_partial'    => true,
            'currency_code' => 'EUR',
            'paid_at'       => now()->subDay(),
        ]);
        $bill->update(['remaining_balance' => 40.00, 'last_paid_date' => now()->subDay()->toDateString()]);

        $this->actingAs($user)->get(route('bills.index'))->assertOk();
        $this->actingAs($user)->get(route('bills.show', $bill))->assertOk();
        $this->actingAs($user)->get(route('bills.create'))->assertOk();
        $this->actingAs($user)->get(route('bills.edit', $bill))->assertOk();
    }
}
