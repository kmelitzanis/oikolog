<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\HouseholdReport;
use App\Support\XlsxWriter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Charts of spending and income over a period, and the same data as a spreadsheet. */
class ReportController extends Controller
{
    public function index(Request $request)
    {
        $report = $this->report($request);

        return view('reports.index', [
            'report'   => $report,
            'periods'  => HouseholdReport::periods($request->user()),
            'monthly'  => $report->monthly(),
            'totals'   => $report->totals(),
            'categories' => $report->byCategory(),
            'topBills' => $report->topBills(),
            'incomeSources' => $report->incomeBySource(),
            'currency' => $request->user()->currency_code ?? 'EUR',
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $report = $this->report($request);
        $t = fn (string $key) => __('messages.' . $key);

        $monthly = collect($report->monthly())->map(fn ($m) => [
            $m['month']->translatedFormat('F Y'), $m['bills'], $m['purchases'], $m['vehicles'], $m['spent'], $m['income'], $m['net'],
        ])->values();
        $totals = $report->totals();
        $monthly->push([XlsxWriter::total($t('reports_total')), $totals['bills'], $totals['purchases'], $totals['vehicles'], $totals['spent'], $totals['income'], $totals['net']]);

        $categories = $report->byCategory();
        $catTotal = $categories->sum('amount') ?: 1;

        $xlsx = (new XlsxWriter())
            ->addSheet($t('reports_sheet_monthly'),
                [$t('reports_month'), $t('bills'), $t('expenses_menu'), $t('vehicles'), $t('reports_spent'), $t('income'), $t('reports_net')],
                $monthly, ['text', 'money', 'money', 'money', 'money', 'money', 'money'])
            ->addSheet($t('reports_sheet_categories'),
                [$t('category'), $t('reports_source'), $t('amount'), $t('reports_share')],
                $categories->map(fn ($c) => [$c['label'], $c['source'], $c['amount'], $c['amount'] / $catTotal])
                    ->push([XlsxWriter::total($t('reports_total')), '', round($categories->sum('amount'), 2), $categories->isEmpty() ? 0 : 1]),
                ['text', 'text', 'money', 'percent'])
            ->addSheet($t('reports_sheet_payments'),
                [$t('date'), $t('reports_bill'), $t('category'), $t('amount'), $t('reports_currency'), $t('reports_paid_by'), $t('from_account'), $t('reports_partial'), $t('notes')],
                $report->payments->map(fn ($p) => [
                    $p->paid_at, $p->bill?->name, $p->bill?->category?->name, (float) $p->amount, $p->currency_code,
                    $p->paidBy?->name, $p->account?->name, $p->is_partial ? $t('yes') : '', $p->notes,
                ]),
                ['date', 'text', 'text', 'money'])
            ->addSheet($t('expenses_menu'),
                [$t('date'), $t('name'), $t('category'), $t('amount'), $t('from_account'), $t('notes')],
                $report->purchases->map(fn ($e) => [
                    $e->spent_at, $e->name, __('messages.expense_category_' . $e->category), (float) $e->amount, $e->account?->name, $e->notes,
                ]),
                ['date', 'text', 'text', 'money'])
            ->addSheet($t('vehicles'),
                [$t('date'), $t('reports_vehicle'), $t('category'), $t('amount'), 'km', $t('reports_litres'), $t('notes')],
                $report->vehicleCosts->map(fn ($v) => [
                    $v->spent_at, $v->vehicle?->name, __('messages.vehicle_expense_' . $v->category), (float) $v->amount,
                    $v->odometer_km, $v->litres !== null ? (float) $v->litres : null, $v->note,
                ]),
                ['date', 'text', 'text', 'money', 'number', 'number'])
            ->addSheet($t('income'),
                [$t('date'), $t('income'), $t('amount'), $t('reports_into_account')],
                $report->incomes->map(fn ($d) => [$d->occurred_at, $d->income?->name ?? $d->description, (float) $d->amount, $d->account?->name]),
                ['date', 'text', 'money']);

        $format = $report->period === 'custom' ? 'Y-m-d' : 'Y-m';
        $name = 'oikolog-' . $report->from->format($format) . '_' . $report->to->format($format) . '.xlsx';

        return response()->download($xlsx->save(), $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    private function report(Request $request): HouseholdReport
    {
        $period = (string) $request->query('period', '12m');
        if (! HouseholdReport::isValidPeriod($period)) {
            $period = '12m';
        }

        if ($period === 'custom') {
            $from = $this->day($request->query('from'));
            $to = $this->day($request->query('to'));
            if (! $from || ! $to) {
                return new HouseholdReport($request->user(), '12m');
            }
            if ($from->gt($to)) {
                [$from, $to] = [$to, $from];
            }
            // A bound on the work one request can ask for.
            if ($from->lt($to->subYears(20))) {
                $from = $to->subYears(20);
            }

            return new HouseholdReport($request->user(), 'custom', $from, $to);
        }

        return new HouseholdReport($request->user(), $period);
    }

    private function day(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        return $day && $day->format('Y-m-d') === $value ? $day : null;
    }
}
