<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use App\Support\HouseholdReport;
use App\Support\XlsxWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private function household(User $user): void
    {
        $category = Category::create(['name' => 'Ενοίκιο', 'is_system' => false]);
        $bill = Bill::create([
            'name' => 'ΕΝΟΙΚΙΟ', 'category_id' => $category->id, 'created_by' => $user->id, 'amount' => 450,
            'currency_code' => 'EUR', 'frequency' => 'monthly', 'start_date' => now()->subYear()->toDateString(),
            'next_due_date' => now()->addDays(5)->toDateString(), 'is_active' => true,
        ]);
        foreach ([0, 1] as $ago) {
            Payment::create(['bill_id' => $bill->id, 'paid_by' => $user->id, 'amount' => 450, 'is_partial' => false,
                'currency_code' => 'EUR', 'paid_at' => now()->startOfMonth()->subMonths($ago)->addDays(2)]);
        }

        Expense::create(['name' => 'Σούπερ μάρκετ', 'category' => 'groceries', 'amount' => 80, 'currency_code' => 'EUR',
            'spent_at' => now()->startOfMonth()->addDay(), 'created_by' => $user->id]);

        $vehicle = Vehicle::create(['name' => 'Polo', 'type' => 'car', 'created_by' => $user->id, 'is_active' => true]);
        VehicleExpense::create(['vehicle_id' => $vehicle->id, 'category' => 'fuel', 'amount' => 50,
            'spent_at' => now()->startOfMonth()->toDateString(), 'created_by' => $user->id]);

        $account = Account::create(['name' => 'Μισθοδοσίας', 'opening_balance' => 0, 'currency_code' => 'EUR', 'created_by' => $user->id]);
        $income = Income::create(['name' => 'Μισθός', 'amount' => 1500, 'currency_code' => 'EUR', 'frequency' => 'monthly',
            'start_date' => now()->subYear()->toDateString(), 'next_date' => now()->addMonth()->toDateString(),
            'is_active' => true, 'created_by' => $user->id, 'account_id' => $account->id]);
        AccountTransaction::create(['account_id' => $account->id, 'type' => 'deposit', 'amount' => 1500, 'direction' => 1,
            'currency_code' => 'EUR', 'occurred_at' => now()->startOfMonth(), 'income_id' => $income->id, 'created_by' => $user->id]);
    }

    public function test_the_report_adds_up_spending_and_income_by_month(): void
    {
        $user = User::factory()->create();
        $this->household($user);
        // Someone else's purchase is not part of it.
        Expense::create(['name' => 'x', 'category' => 'other', 'amount' => 999, 'currency_code' => 'EUR',
            'spent_at' => now(), 'created_by' => User::factory()->create()->id]);

        $report = new HouseholdReport($user, '12m');
        $months = $report->monthly();
        $this->assertCount(12, $months);

        $now = $months[now()->format('Y-m')];
        $this->assertSame(450.0, $now['bills']);
        $this->assertSame(80.0, $now['purchases']);
        $this->assertSame(50.0, $now['vehicles']);
        $this->assertSame(580.0, $now['spent']);
        $this->assertSame(1500.0, $now['income']);
        $this->assertSame(920.0, $now['net']);

        $totals = $report->totals();
        $this->assertSame(1030.0, $totals['spent']);
        $this->assertSame(470.0, $totals['net']);
        $this->assertSame('Ενοίκιο', $report->byCategory()->first()['label']);
    }

    public function test_a_year_covers_january_to_december(): void
    {
        [$from, $to] = HouseholdReport::range('2025');
        $this->assertSame('2025-01-01', $from->toDateString());
        $this->assertSame('2025-12-31', $to->toDateString());
        $this->assertFalse(HouseholdReport::isValidPeriod('drop table'));
    }

    public function test_a_custom_range_counts_only_its_own_days(): void
    {
        $user = User::factory()->create();
        $this->household($user);
        $from = now()->startOfMonth()->toDateString();
        $to = now()->startOfMonth()->addDay()->toDateString();

        // The 1st and 2nd of this month: the salary, fuel and groceries, not the rent on the 3rd.
        $report = new HouseholdReport($user, 'custom', \Carbon\CarbonImmutable::parse($from), \Carbon\CarbonImmutable::parse($to));
        $this->assertCount(1, $report->months());
        $this->assertSame(130.0, $report->totals()['spent']);
        $this->assertSame(1500.0, $report->totals()['income']);

        $this->actingAs($user)->get(route('reports.index', ['period' => 'custom', 'from' => $from, 'to' => $to]))
            ->assertOk()
            ->assertSee(route('reports.export', ['period' => 'custom', 'from' => $from, 'to' => $to]));

        $this->actingAs($user)->get(route('reports.export', ['period' => 'custom', 'from' => $from, 'to' => $to]))
            ->assertOk()
            ->assertDownload("oikolog-{$from}_{$to}.xlsx");
    }

    public function test_a_custom_range_given_backwards_is_turned_round(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('reports.index', ['period' => 'custom', 'from' => '2025-12-31', 'to' => '2025-01-01']))
            ->assertOk()
            ->assertSee('01/01/2025', false);
    }

    public function test_a_broken_custom_range_falls_back_to_twelve_months(): void
    {
        $user = User::factory()->create();

        foreach ([['from' => '2025-02-30', 'to' => '2025-03-01'], ['from' => 'x'], []] as $query) {
            $this->actingAs($user)->get(route('reports.index', ['period' => 'custom', ...$query]))
                ->assertOk()
                ->assertSee(route('reports.export', ['period' => '12m']), false);
        }
    }

    public function test_the_page_shows_the_charts(): void
    {
        $user = User::factory()->create();
        $this->household($user);

        $this->actingAs($user)->get(route('reports.index'))
            ->assertOk()
            ->assertSee('oikoChart', false)
            ->assertSee(route('reports.export', ['period' => '12m']), false);

        // An unknown period falls back rather than failing.
        $this->actingAs($user)->get(route('reports.index', ['period' => 'nope']))->assertOk();
    }

    public function test_an_empty_household_still_gets_a_page(): void
    {
        $this->actingAs(User::factory()->create())->get(route('reports.index'))->assertOk();
    }

    public function test_the_export_is_a_workbook_with_a_sheet_per_kind(): void
    {
        $user = User::factory()->create();
        $this->household($user);

        $response = $this->actingAs($user)->get(route('reports.export', ['period' => '12m']));
        $response->assertOk();
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($response->getFile()->getPathname()));
        $workbook = $zip->getFromName('xl/workbook.xml');
        $this->assertSame(6, substr_count($workbook, '<sheet '));
        $this->assertNotFalse(simplexml_load_string($zip->getFromName('xl/styles.xml')));

        $payments = $zip->getFromName('xl/worksheets/sheet3.xml');
        $this->assertNotFalse(simplexml_load_string($payments));
        $this->assertStringContainsString('ΕΝΟΙΚΙΟ', $payments);
        $this->assertStringContainsString('<v>450</v>', $payments);
        $zip->close();
    }

    public function test_the_writer_escapes_text_and_writes_dates_as_numbers(): void
    {
        $path = (new XlsxWriter())
            ->addSheet('A/B:C', ['Date', 'Text', 'Amount'], [['2024-08-21', 'Tom & <Jerry>', 12.5]], ['date', 'text', 'money'])
            ->save();

        $zip = new ZipArchive();
        $zip->open($path);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertNotFalse(simplexml_load_string($sheet));
        $this->assertStringContainsString('<v>45525</v>', $sheet); // 21/08/2024
        $this->assertStringContainsString('Tom &amp; &lt;Jerry&gt;', $sheet);
        $this->assertStringContainsString('name="A B C"', $zip->getFromName('xl/workbook.xml'));
        $zip->close();
        unlink($path);
    }

    public function test_guests_cannot_see_reports(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
        $this->get(route('reports.export'))->assertRedirect(route('login'));
    }
}
