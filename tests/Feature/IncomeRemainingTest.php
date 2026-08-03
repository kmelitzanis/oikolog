<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Category;
use App\Models\Income;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bill payments funded from an income source have to reduce what that source
 * still shows — on the list, on its detail page, and in the picker you choose
 * it from while paying.
 */
class IncomeRemainingTest extends TestCase
{
    use RefreshDatabase;

    private function seedPaidBill(User $user, Income $income, float $amount): void
    {
        $category = Category::create(['name' => 'Housing', 'is_system' => false]);

        $bill = Bill::create([
            'name'          => 'Rent',
            'category_id'   => $category->id,
            'created_by'    => $user->id,
            'amount'        => $amount,
            'currency_code' => 'EUR',
            'frequency'     => 'monthly',
            'start_date'    => now()->subMonths(3)->toDateString(),
            'next_due_date' => now()->addDays(20)->toDateString(),
            'is_active'     => true,
        ]);

        Payment::create([
            'bill_id'       => $bill->id,
            'paid_by'       => $user->id,
            'income_id'     => $income->id,
            'amount'        => $amount,
            'is_partial'    => false,
            'currency_code' => 'EUR',
            'paid_at'       => now(),
        ]);
    }

    private function makeIncome(User $user): Income
    {
        return Income::create([
            'name'          => 'Salary',
            'amount'        => 1200,
            'currency_code' => 'EUR',
            'frequency'     => 'monthly',
            'start_date'    => now()->subDays(2)->toDateString(),
            'next_date'     => now()->addMonth()->toDateString(),
            'is_active'     => true,
            'created_by'    => $user->id,
        ]);
    }

    public function test_paying_a_bill_reduces_the_income_balance(): void
    {
        $user   = User::factory()->create(['currency_code' => 'EUR']);
        $income = $this->makeIncome($user);
        $this->seedPaidBill($user, $income, 450);

        $this->assertSame(450.0, $income->spentThisPeriod());
        $this->assertSame(750.0, $income->remainingThisPeriod());
    }

    public function test_income_list_and_detail_lead_with_the_remaining_balance(): void
    {
        $user   = User::factory()->create(['currency_code' => 'EUR']);
        $income = $this->makeIncome($user);
        $this->seedPaidBill($user, $income, 450);

        $this->actingAs($user)->get('/income')
            ->assertOk()
            ->assertSee('750.00');   // per-source remaining + grand total

        $this->actingAs($user)->get('/income/' . $income->id)
            ->assertOk()
            ->assertSee('750.00');
    }

    public function test_pay_modal_picker_shows_each_income_remaining(): void
    {
        $user   = User::factory()->create(['currency_code' => 'EUR']);
        $income = $this->makeIncome($user);
        $this->seedPaidBill($user, $income, 450);

        $this->actingAs($user)->get('/bills')
            ->assertOk()
            ->assertSee('Salary · €750.00');
    }
}
