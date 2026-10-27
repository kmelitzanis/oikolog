<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Income;
use App\Services\BillPayments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Bill::with(['category', 'assignee'])
            ->forUser($request->user())
            ->orderBy('next_due_date');

        if ($request->boolean('overdue')) {
            $query->overdue();
        }
        if ($request->filled('frequency')) {
            $query->where('frequency', $request->frequency);
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $bills = $query->paginate(min(100, max(1, $request->integer('per_page', 50))));

        return response()->json([
            'data' => $bills->map(fn($b) => $this->billResource($b)),
            'meta' => [
                'total'        => $bills->total(),
                'per_page'     => $bills->perPage(),
                'current_page' => $bills->currentPage(),
                'last_page'    => $bills->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'               => ['required', 'string', 'max:120'],
            'description'        => ['nullable', 'string', 'max:2000'],
            'category_id'        => ['required', 'exists:categories,id'],
            'assigned_to'        => ['nullable', 'string', $this->householdMember($request)],
            'amount'             => ['required', 'numeric', 'min:0.01'],
            'frequency'          => ['required', 'in:once,daily,weekly,biweekly,monthly,quarterly,yearly'],
            'frequency_interval' => ['nullable', 'integer', 'min:1', 'max:99'],
            'start_date'         => ['required', 'date'],
            'end_date'           => ['nullable', 'date', 'after:start_date'],
            'is_shared'          => ['nullable', 'boolean'],
            'notify_enabled'     => ['nullable', 'boolean'],
            'notify_days_before' => ['nullable', 'integer', 'min:1', 'max:30'],
            'url'                => ['nullable', 'url:http,https', 'max:2048'],
            'notes'              => ['nullable', 'string', 'max:5000'],
        ]);

        $shared = (bool) ($data['is_shared'] ?? false) && $request->user()->family_id;

        $bill = Bill::create([
            ...$data,
            'is_shared'     => $shared,
            'created_by'    => $request->user()->id,
            'currency_code' => $request->user()->currency_code,
            'family_id'     => $shared ? $request->user()->family_id : null,
            'next_due_date' => $data['start_date'],
        ]);

        return response()->json(
            ['data' => $this->billResource($bill->load('category'))], 201
        );
    }

    public function show(Request $request, Bill $bill): JsonResponse
    {
        $this->authorizeView($request, $bill);
        $bill->load(['category', 'assignee', 'payments.paidBy']);
        return response()->json(['data' => $this->billResource($bill)]);
    }

    public function update(Request $request, Bill $bill): JsonResponse
    {
        $this->authorizeEdit($request, $bill);

        $data = $request->validate([
            'name'               => ['sometimes', 'string', 'max:120'],
            'description'        => ['nullable', 'string', 'max:2000'],
            'category_id'        => ['sometimes', 'exists:categories,id'],
            'assigned_to'        => ['nullable', 'string', $this->householdMember($request)],
            'amount'             => ['sometimes', 'numeric', 'min:0.01'],
            'frequency'          => ['sometimes', 'in:once,daily,weekly,biweekly,monthly,quarterly,yearly'],
            'frequency_interval' => ['sometimes', 'integer', 'min:1', 'max:99'],
            'start_date'         => ['sometimes', 'date'],
            'end_date'           => ['nullable', 'date'],
            'next_due_date'      => ['sometimes', 'date'],
            'is_active'          => ['sometimes', 'boolean'],
            'is_shared'          => ['sometimes', 'boolean'],
            'notify_enabled'     => ['sometimes', 'boolean'],
            'notify_days_before' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'url'                => ['nullable', 'url:http,https', 'max:2048'],
            'notes'              => ['nullable', 'string', 'max:5000'],
        ]);

        if (isset($data['is_shared'])) {
            $data['is_shared'] = $data['is_shared'] && $request->user()->family_id;
            $data['family_id'] = $data['is_shared'] ? $request->user()->family_id : null;
        }

        // If the recurrence schedule changed (and the caller didn't set an
        // explicit next_due_date), snap next_due_date back onto the new cadence.
        $scheduleChanged = (isset($data['frequency']) && $data['frequency'] !== $bill->frequency)
            || (array_key_exists('frequency_interval', $data) && (int) $data['frequency_interval'] !== (int) $bill->frequency_interval)
            || (isset($data['start_date']) && \Carbon\Carbon::parse($data['start_date'])->toDateString() !== optional($bill->start_date)->toDateString());

        $bill->update($data);

        if ($scheduleChanged && ! isset($data['next_due_date'])) {
            $bill->realignNextDueDate();
            $bill->save();
        }

        return response()->json(['data' => $this->billResource($bill->load('category'))]);
    }

    public function destroy(Request $request, Bill $bill): JsonResponse
    {
        $this->authorizeEdit($request, $bill);
        $bill->delete();
        return response()->json(['message' => 'Bill deleted.']);
    }

    /**
     * Pay the bill's current cycle. Same fields and the same rules as the web
     * pay modal (account, payer, partial payments, loan draw-down), plus
     * `amount` for older clients: on a varying bill it is this cycle's
     * figure, otherwise an amount short of what is due pays part of it.
     */
    public function markPaid(Request $request, Bill $bill, BillPayments $payments): JsonResponse
    {
        $this->authorizeView($request, $bill);

        // A client that names no account pays from the bill's own default.
        if (! $request->filled('account_id') && $bill->default_account_id) {
            $request->merge(['account_id' => $bill->default_account_id]);
        }

        $data = $request->validate([
            ...$payments->rules($request->user()),
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:99999999'],
        ]);

        if (isset($data['amount']) && ! isset($data['payment_mode'])) {
            if ($bill->cost_varies && $bill->remaining_balance === null) {
                $data['custom_amount'] ??= $data['amount'];
            } elseif ((float) $data['amount'] < $bill->amountDueNow()) {
                $data['payment_mode'] = 'partial';
                $data['partial_amount'] = $data['amount'];
            }
        }

        [, $isPartial] = $payments->pay($bill, $request->user(), $data);

        return response()->json([
            'data'    => $this->billResource($bill->fresh()->load('category')),
            'status'  => $isPartial ? 'partial' : 'paid',
            'message' => $isPartial ? 'Partial payment recorded.' : 'Payment recorded.',
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $user  = $request->user();
        $bills = Bill::forUser($user)->active()->with('category')->get();

        $monthlySpend = $bills->sum(fn($b) => $b->monthlyEquivalent());
        $byCategory   = $bills->groupBy('category.name')
            ->map(fn($g) => round($g->sum(fn($b) => $b->monthlyEquivalent()), 2))
            ->sortDesc();

        $upcoming = Bill::forUser($user)->dueWithin(30)->with('category')
            ->orderBy('next_due_date')->take(10)->get()
            ->map(fn($b) => $this->billResource($b));

        // Income
        $incomes = Income::forUser($user)->active()->get();
        $monthlyIncome = $incomes->sum(fn($i) => $i->monthlyEquivalent());

        return response()->json([
            'monthly_total' => round($monthlySpend, 2),
            'yearly_total' => round($monthlySpend * 12, 2),
            'monthly_income' => round($monthlyIncome, 2),
            'yearly_income' => round($monthlyIncome * 12, 2),
            'monthly_net' => round($monthlyIncome - $monthlySpend, 2),
            'active_count' => $bills->count(),
            // status(), as on the dashboard: the raw dates kept counting bills
            // that were already paid.
            'overdue_count' => $bills->filter(fn($b) => $b->status() === 'overdue')->count(),
            'due_this_week' => $bills->filter(fn($b) => $b->status() === 'soon')->count(),
            'currency_code' => $user->currency_code,
            'by_category' => $byCategory,
            'upcoming' => $upcoming,
        ]);
    }

    public function series(Request $request): JsonResponse
    {
        $user = $request->user();
        $months = max(1, min(36, (int)$request->integer('months', 12)));

        // Build month labels going back from current month
        $labels = [];
        $now = now()->startOfMonth();
        for ($i = $months - 1; $i >= 0; $i--) {
            $labels[] = $now->copy()->subMonths($i)->format('Y-m');
        }
        $labelIndex = array_flip($labels);

        $spending = array_fill(0, count($labels), 0.0);
        $income = array_fill(0, count($labels), 0.0);

        $windowStart = now()->copy()->startOfMonth()->subMonths($months - 1)->startOfDay();
        $windowEnd = now()->copy()->endOfMonth()->endOfDay();

        // Spending: bills
        $bills = Bill::forUser($user)->active()->get();
        foreach ($bills as $bill) {
            foreach ($bill->occurrencesBetween($windowStart, $windowEnd) as $occ) {
                $key = $occ->format('Y-m');
                if (isset($labelIndex[$key])) {
                    $spending[$labelIndex[$key]] += (float)$bill->amount;
                }
            }
        }

        // Income: Income model
        $incomes = Income::forUser($user)->active()->get();
        foreach ($incomes as $inc) {
            foreach ($inc->occurrencesBetween($windowStart, $windowEnd) as $occ) {
                $key = $occ->format('Y-m');
                if (isset($labelIndex[$key])) {
                    $income[$labelIndex[$key]] += (float)$inc->amount;
                }
            }
        }

        return response()->json([
            'months' => $labels,
            'spending' => array_map(fn($v) => round($v, 2), $spending),
            'income' => array_map(fn($v) => round($v, 2), $income),
            'currency_code' => $user->currency_code,
        ]);
    }

    public function payments(Request $request, Bill $bill): JsonResponse
    {
        $this->authorizeView($request, $bill);

        $payments = $bill->payments()->with('paidBy')->orderByDesc('paid_at')->paginate(20);

        return response()->json([
            'data' => $payments->map(fn($p) => [
                'id'            => $p->id,
                'amount'        => (float) $p->amount,
                'currency_code' => $p->currency_code,
                'paid_at'       => $p->paid_at?->toIso8601String(),
                'notes'         => $p->notes,
                'paid_by'       => ['id' => $p->paidBy?->id, 'name' => $p->paidBy?->name],
            ]),
        ]);
    }

    /** The user themself or someone in their family. */
    private function householdMember(Request $request): \Closure
    {
        return function (string $attribute, $value, \Closure $fail) use ($request) {
            $user = $request->user();
            $ok = $value === $user->id
                || ($user->family_id && \App\Models\User::whereKey($value)->where('family_id', $user->family_id)->exists());

            if (! $ok) {
                $fail(__('messages.invalid_selection'));
            }
        };
    }

    private function authorizeView(Request $request, Bill $bill): void
    {
        abort_unless($bill->isVisibleTo($request->user()), 403, 'Access denied.');
    }

    /** Editing is open to whoever can see it — see the web controller. */
    private function authorizeEdit(Request $request, Bill $bill): void
    {
        $this->authorizeView($request, $bill);
    }

    private function billResource(Bill $bill): array
    {
        return [
            'id'                 => $bill->id,
            'name'               => $bill->name,
            'description'        => $bill->description,
            'amount'             => (float) $bill->amount,
            'currency_code'      => $bill->currency_code,
            'frequency'          => $bill->frequency,
            'frequency_interval' => $bill->frequency_interval,
            'start_date'         => $bill->start_date?->toDateString(),
            'end_date'           => $bill->end_date?->toDateString(),
            'next_due_date'      => $bill->next_due_date?->toDateString(),
            'last_paid_date'     => $bill->last_paid_date?->toDateString(),
            'is_active'          => $bill->is_active,
            'is_shared'          => $bill->is_shared,
            'notify_enabled'     => $bill->notify_enabled,
            'notify_days_before' => $bill->notify_days_before,
            'url'                => $bill->url,
            'notes'              => $bill->notes,
            'is_overdue'         => $bill->isOverdue(),
            'days_until_due'     => $bill->daysUntilDue(),
            'monthly_equivalent' => round($bill->monthlyEquivalent(), 2),
            'category'           => $bill->relationLoaded('category') && $bill->category ? [
                'id'        => $bill->category->id,
                'name'      => $bill->category->name,
                'icon'      => $bill->category->icon,
                'color_hex' => $bill->category->color_hex,
            ] : null,
            'payments' => $bill->relationLoaded('payments')
                ? $bill->payments->take(5)->map(fn($p) => [
                    'id'      => $p->id,
                    'amount'  => (float) $p->amount,
                    'paid_at' => $p->paid_at?->toIso8601String(),
                ])->values()
                : null,
            'created_at' => $bill->created_at?->toIso8601String(),
            'updated_at' => $bill->updated_at?->toIso8601String(),
        ];
    }
}
