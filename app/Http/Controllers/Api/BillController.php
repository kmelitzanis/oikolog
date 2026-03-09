<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $bills = $query->paginate($request->integer('per_page', 50));

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
            'description'        => ['nullable', 'string'],
            'category_id'        => ['required', 'exists:categories,id'],
            'assigned_to'        => ['nullable', 'exists:users,id'],
            'amount'             => ['required', 'numeric', 'min:0.01'],
            'frequency'          => ['required', 'in:once,daily,weekly,biweekly,monthly,quarterly,yearly'],
            'frequency_interval' => ['nullable', 'integer', 'min:1', 'max:99'],
            'start_date'         => ['required', 'date'],
            'end_date'           => ['nullable', 'date', 'after:start_date'],
            'is_shared'          => ['nullable', 'boolean'],
            'notify_enabled'     => ['nullable', 'boolean'],
            'notify_days_before' => ['nullable', 'integer', 'min:1', 'max:30'],
            'url'                => ['nullable', 'url'],
            'notes'              => ['nullable', 'string'],
        ]);

        $bill = Bill::create([
            ...$data,
            'created_by'    => $request->user()->id,
            'currency_code' => $request->user()->currency_code,
            'family_id'     => ($data['is_shared'] ?? false) ? $request->user()->family_id : null,
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
            'description'        => ['nullable', 'string'],
            'category_id'        => ['sometimes', 'exists:categories,id'],
            'assigned_to'        => ['nullable', 'exists:users,id'],
            'amount'             => ['sometimes', 'numeric', 'min:0.01'],
            'frequency'          => ['sometimes', 'in:once,daily,weekly,biweekly,monthly,quarterly,yearly'],
            'frequency_interval' => ['nullable', 'integer', 'min:1'],
            'start_date'         => ['sometimes', 'date'],
            'end_date'           => ['nullable', 'date'],
            'next_due_date'      => ['sometimes', 'date'],
            'is_active'          => ['sometimes', 'boolean'],
            'is_shared'          => ['sometimes', 'boolean'],
            'notify_enabled'     => ['sometimes', 'boolean'],
            'notify_days_before' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'url'                => ['nullable', 'url'],
            'notes'              => ['nullable', 'string'],
        ]);

        if (isset($data['is_shared'])) {
            $data['family_id'] = $data['is_shared'] ? $request->user()->family_id : null;
        }

        $bill->update($data);

        return response()->json(['data' => $this->billResource($bill->load('category'))]);
    }

    public function destroy(Request $request, Bill $bill): JsonResponse
    {
        $this->authorizeEdit($request, $bill);
        $bill->delete();
        return response()->json(['message' => 'Bill deleted.']);
    }

    public function markPaid(Request $request, Bill $bill): JsonResponse
    {
        $this->authorizeView($request, $bill);

        $data = $request->validate([
            'amount'        => ['nullable', 'numeric', 'min:0.01'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'paid_at'       => ['nullable', 'date'],
            'notes'         => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($bill, $request, $data) {
            Payment::create([
                'bill_id'       => $bill->id,
                'paid_by'       => $request->user()->id,
                'amount'        => $data['amount'] ?? $bill->amount,
                'currency_code' => $data['currency_code'] ?? $bill->currency_code,
                'paid_at'       => $data['paid_at'] ?? now(),
                'notes'         => $data['notes'] ?? null,
            ]);

            $bill->update([
                'last_paid_date' => now()->toDateString(),
                'next_due_date'  => $bill->calculateNextDueDate()->toDateString(),
            ]);
        });

        return response()->json([
            'data'    => $this->billResource($bill->fresh()->load('category')),
            'message' => 'Payment recorded.',
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $user  = $request->user();
        $bills = Bill::forUser($user)->active()->with('category')->get();

        $monthlyTotal = $bills->sum(fn($b) => $b->monthlyEquivalent());
        $byCategory   = $bills->groupBy('category.name')
            ->map(fn($g) => round($g->sum(fn($b) => $b->monthlyEquivalent()), 2))
            ->sortDesc();

        $upcoming = Bill::forUser($user)->dueWithin(30)->with('category')
            ->orderBy('next_due_date')->take(10)->get()
            ->map(fn($b) => $this->billResource($b));

        return response()->json([
            'monthly_total' => round($monthlyTotal, 2),
            'yearly_total'  => round($monthlyTotal * 12, 2),
            'active_count'  => $bills->count(),
            'overdue_count' => $bills->filter(fn($b) => $b->isOverdue())->count(),
            'due_this_week' => $bills->filter(fn($b) => $b->daysUntilDue() >= 0 && $b->daysUntilDue() <= 7)->count(),
            'currency_code' => $user->currency_code,
            'by_category'   => $byCategory,
            'upcoming'      => $upcoming,
        ]);
    }

    public function series(Request $request): JsonResponse
    {
        $user = $request->user();
        $months = (int)$request->integer('months', 12);
        $months = max(1, min(36, $months));

        // Get active bills for user
        $bills = Bill::forUser($user)->active()->with('category')->get();

        // Build month labels (YYYY-MM) starting from current month going back
        $labels = [];
        $now = now()->startOfMonth();
        for ($i = $months - 1; $i >= 0; $i--) {
            $labels[] = $now->copy()->subMonths($i)->format('Y-m');
        }

        // Map labels to indexes for quick lookup
        $labelIndex = array_flip($labels);

        // Initialize series arrays
        $spending = array_fill(0, count($labels), 0.0);
        $income = array_fill(0, count($labels), 0.0);

        // Determine the date window to generate occurrences
        $windowStart = now()->copy()->startOfMonth()->subMonths($months - 1)->startOfDay();
        $windowEnd = now()->copy()->endOfMonth()->endOfDay();

        // For each bill, generate occurrences in the window and add amounts to the month buckets
        foreach ($bills as $bill) {
            $occurrences = $bill->occurrencesBetween($windowStart, $windowEnd);
            if (empty($occurrences)) continue;

            foreach ($occurrences as $occ) {
                $monthKey = $occ->format('Y-m');
                if (!isset($labelIndex[$monthKey])) continue; // outside window

                $idx = $labelIndex[$monthKey];
                $amount = (float)$bill->amount;
                if ($amount < 0) {
                    $income[$idx] += abs($amount);
                } else {
                    $spending[$idx] += $amount;
                }
            }
        }

        // Round values to 2 decimals
        $spending = array_map(fn($v) => round($v, 2), $spending);
        $income = array_map(fn($v) => round($v, 2), $income);

        return response()->json([
            'months' => $labels,
            'spending' => $spending,
            'income' => $income,
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

    private function authorizeView(Request $request, Bill $bill): void
    {
        $user    = $request->user();
        $canView = $bill->created_by === $user->id
            || ($bill->is_shared && $bill->family_id === $user->family_id);
        abort_unless($canView, 403, 'Access denied.');
    }

    private function authorizeEdit(Request $request, Bill $bill): void
    {
        $user    = $request->user();
        $canEdit = $bill->created_by === $user->id || $user->isFamilyAdmin();
        abort_unless($canEdit, 403, 'You cannot edit this bill.');
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
