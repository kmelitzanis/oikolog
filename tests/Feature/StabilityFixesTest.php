<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Bill;
use App\Models\Category;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StabilityFixesTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attrs = []): User
    {
        return User::factory()->create(array_merge(['currency_code' => 'EUR', 'locale' => 'en'], $attrs));
    }

    private function bill(User $user, array $attrs = []): Bill
    {
        return Bill::create(array_merge([
            'name'          => 'Rent',
            'category_id'   => Category::firstOrCreate(['name' => 'Home'])->id,
            'created_by'    => $user->id,
            'amount'        => 500,
            'currency_code' => 'EUR',
            'frequency'     => 'monthly',
            'start_date'    => now()->toDateString(),
            'next_due_date' => now()->toDateString(),
            'is_active'     => true,
        ], $attrs));
    }

    // ── Dates ────────────────────────────────────────────────────────────

    public function test_a_bill_due_on_the_31st_stays_on_the_last_day_of_the_month(): void
    {
        $bill = $this->bill($this->user(), [
            'start_date'    => '2027-01-31',
            'next_due_date' => '2027-01-31',
        ]);

        $this->assertSame('2027-02-28', $bill->calculateNextDueDate()->toDateString());

        $bill->next_due_date = '2027-02-28';
        $this->assertSame('2027-03-31', $bill->calculateNextDueDate()->toDateString());

        $dates = array_map(
            fn (Carbon $d) => $d->toDateString(),
            $bill->occurrencesBetween(Carbon::parse('2027-01-01'), Carbon::parse('2027-05-31')),
        );
        $this->assertSame(['2027-01-31', '2027-02-28', '2027-03-31', '2027-04-30', '2027-05-31'], $dates);
    }

    public function test_a_long_daily_schedule_does_not_blow_the_stack(): void
    {
        $bill = $this->bill($this->user(), [
            'frequency'  => 'daily',
            'start_date' => now()->subYears(20)->toDateString(),
        ]);

        $this->assertCount(31, $bill->occurrencesBetween(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31')));
    }

    // ── Calendar ─────────────────────────────────────────────────────────

    public function test_calendar_colours_follow_the_real_status(): void
    {
        Carbon::setTestNow('2026-10-06 12:00:00');
        $user = $this->user();
        $this->bill($user, ['name' => 'Today', 'start_date' => '2026-10-06', 'next_due_date' => '2026-10-06', 'frequency' => 'once']);
        $this->bill($user, ['name' => 'Later', 'start_date' => '2026-10-25', 'next_due_date' => '2026-10-25', 'frequency' => 'once']);

        $events = collect($this->actingAs($user)
            ->getJson(route('bills.events', ['start' => '2026-10-01', 'end' => '2026-10-31']))
            ->assertOk()->json())->keyBy(fn ($e) => trim($e['title'], '• '));

        $this->assertFalse($events['Today']['extendedProps']['overdue'], 'Due today is not overdue.');
        $this->assertTrue($events['Today']['extendedProps']['soon']);
        $this->assertFalse($events['Later']['extendedProps']['soon'], 'Three weeks away is not "soon".');
    }

    public function test_calendar_survives_bad_dates_and_caps_the_window(): void
    {
        $user = $this->user();
        $this->bill($user, ['frequency' => 'daily', 'start_date' => now()->subYears(5)->toDateString()]);

        $this->actingAs($user)->getJson(route('bills.events', ['start' => 'not-a-date', 'end' => 'nope']))->assertOk();

        $events = $this->actingAs($user)
            ->getJson(route('bills.events', ['start' => '1900-01-01', 'end' => '2999-12-31']))
            ->assertOk()->json();

        $this->assertLessThanOrEqual(63, count($events));
    }

    // ── Money ────────────────────────────────────────────────────────────

    public function test_deleting_a_bill_keeps_the_money_it_took_out_of_an_account(): void
    {
        $user = $this->user();
        $account = Account::create(['name' => 'Main', 'created_by' => $user->id, 'opening_balance' => 1000]);
        $bill = $this->bill($user);

        $this->actingAs($user)->post(route('bills.pay', $bill), ['account_id' => $account->id])->assertSessionHasNoErrors();
        $this->assertSame(500.0, $account->balance());

        $this->actingAs($user)->delete(route('bills.destroy', $bill))->assertRedirect();

        $this->assertDatabaseMissing('bills', ['id' => $bill->id]);
        $this->assertSame(500.0, $account->fresh()->balance());
        $this->assertSame(1, AccountTransaction::count());
    }

    public function test_a_paid_one_off_is_not_counted_as_overdue(): void
    {
        $user = $this->user();
        $this->bill($user, [
            'name' => 'Paid ticket', 'frequency' => 'once',
            'next_due_date' => now()->subDays(10)->toDateString(),
            'last_paid_date' => now()->subDays(10)->toDateString(),
        ]);
        $this->bill($user, ['name' => 'Late tax', 'frequency' => 'once', 'next_due_date' => now()->subDays(3)->toDateString()]);

        $this->actingAs($user)->get(route('bills.index', ['status' => 'overdue']))
            ->assertOk()
            ->assertSee('Late tax')
            ->assertDontSee('Paid ticket');
    }

    // ── Receipts ─────────────────────────────────────────────────────────

    public function test_receipts_are_kept_private_to_whoever_can_see_the_bill(): void
    {
        Storage::fake('local');
        $user = $this->user();

        $this->actingAs($user)->post(route('bills.store'), [
            'name' => 'Boiler service', 'category_id' => Category::firstOrCreate(['name' => 'Home'])->id,
            'amount' => 90, 'frequency' => 'once', 'start_date' => now()->toDateString(),
            'receipts' => [UploadedFile::fake()->image('invoice.jpg', 200, 200)],
        ])->assertSessionHasNoErrors();

        $bill = Bill::firstWhere('name', 'Boiler service');
        $receipts = $bill->receiptItems();
        $this->assertCount(1, $receipts);
        $this->assertSame('invoice', $receipts[0]['name']);

        $this->actingAs($user)->get($receipts[0]['url'])->assertOk();
        $this->actingAs($this->user())->get($receipts[0]['url'])->assertForbidden();

        $this->actingAs($user)->delete(route('bills.receipts.destroy', [$bill, $receipts[0]['id']]))->assertRedirect();
        $this->assertSame([], $bill->fresh()->receiptItems());
    }

    public function test_a_receipt_must_be_an_image_or_a_pdf(): void
    {
        Storage::fake('local');
        $user = $this->user();

        $this->actingAs($user)->post(route('bills.store'), [
            'name' => 'Sneaky', 'category_id' => Category::firstOrCreate(['name' => 'Home'])->id,
            'amount' => 1, 'frequency' => 'once', 'start_date' => now()->toDateString(),
            'receipts' => [UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;')],
        ])->assertSessionHasErrors('receipts.0');

        $this->assertDatabaseCount('bills', 0);
    }

    // ── Admin and misc ───────────────────────────────────────────────────

    public function test_an_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->user(['is_admin' => true]);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admins_reach_the_translations_page(): void
    {
        $this->actingAs($this->user(['is_admin' => true]))->get(route('translations.index'))->assertOk();
        $this->actingAs($this->user())->get(route('translations.index'))->assertForbidden();
    }

    public function test_a_garbled_week_in_the_meal_planner_falls_back_to_this_week(): void
    {
        $this->actingAs($this->user())->get(route('meal-plans.index', ['week' => 'garbage']))->assertOk();
    }

    public function test_reset_admin_password_never_uses_a_default(): void
    {
        config(['app.admin_email' => 'boss@example.com']);

        $this->artisan('admin:reset-password', ['--no-interaction' => true])->assertSuccessful();

        $admin = User::firstWhere('email', 'boss@example.com');
        $this->assertTrue($admin->is_admin);
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('changeme123', $admin->password));
    }
}
