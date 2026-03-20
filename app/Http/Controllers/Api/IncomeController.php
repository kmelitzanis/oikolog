<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Income;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncomeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $incomes = Income::forUser($request->user())
            ->orderByDesc('next_date')
            ->paginate($request->integer('per_page', 50));

        return response()->json([
            'data' => $incomes->map(fn($i) => $this->resource($i)),
            'meta' => [
                'total' => $incomes->total(),
                'per_page' => $incomes->perPage(),
                'current_page' => $incomes->currentPage(),
                'last_page' => $incomes->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'source' => ['nullable', 'string', 'max:80'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'frequency' => ['required', 'in:once,daily,weekly,biweekly,monthly,quarterly,yearly'],
            'frequency_interval' => ['nullable', 'integer', 'min:1', 'max:99'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'is_shared' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $income = Income::create([
            ...$data,
            'currency_code' => $request->user()->currency_code,
            'is_shared' => $data['is_shared'] ?? false,
            'created_by' => $request->user()->id,
            'family_id' => ($data['is_shared'] ?? false) ? $request->user()->family_id : null,
            'next_date' => $data['start_date'],
            'frequency_interval' => $data['frequency_interval'] ?? 1,
        ]);

        return response()->json(['data' => $this->resource($income)], 201);
    }

    public function show(Request $request, Income $income): JsonResponse
    {
        $this->gate($request, $income);
        return response()->json(['data' => $this->resource($income)]);
    }

    public function update(Request $request, Income $income): JsonResponse
    {
        $this->gate($request, $income);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'source' => ['nullable', 'string', 'max:80'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'frequency' => ['sometimes', 'in:once,daily,weekly,biweekly,monthly,quarterly,yearly'],
            'frequency_interval' => ['nullable', 'integer', 'min:1'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'is_shared' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        if (isset($data['is_shared'])) {
            $data['family_id'] = $data['is_shared'] ? $request->user()->family_id : null;
        }

        $income->update($data);

        return response()->json(['data' => $this->resource($income->fresh())]);
    }

    public function destroy(Request $request, Income $income): JsonResponse
    {
        $this->gate($request, $income);
        $income->delete();
        return response()->json(['message' => 'Income deleted.']);
    }

    public function markReceived(Request $request, Income $income): JsonResponse
    {
        $this->gate($request, $income);

        $next = $income->calculateNextDate();
        $income->update([
            'last_received_date' => now()->toDateString(),
            'next_date' => $next ? $next->toDateString() : $income->next_date,
        ]);

        return response()->json(['data' => $this->resource($income->fresh()), 'message' => 'Marked as received.']);
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $active = Income::forUser($user)->active()->get();

        $monthly = $active->sum(fn($i) => $i->monthlyEquivalent());

        $bySource = $active->groupBy(fn($i) => $i->source ?: 'Other')
            ->map(fn($g) => round($g->sum(fn($i) => $i->monthlyEquivalent()), 2))
            ->sortDesc();

        return response()->json([
            'monthly_income' => round($monthly, 2),
            'yearly_income' => round($monthly * 12, 2),
            'total_sources' => $active->count(),
            'recurring_count' => $active->filter(fn($i) => $i->frequency !== 'once')->count(),
            'by_source' => $bySource,
            'currency_code' => $user->currency_code,
        ]);
    }

    private function gate(Request $request, Income $income): void
    {
        $user = $request->user();
        $ok = $income->created_by === $user->id
            || ($income->is_shared && $income->family_id === $user->family_id);
        abort_unless($ok, 403, 'Access denied.');
    }

    private function resource(Income $i): array
    {
        return [
            'id' => $i->id,
            'name' => $i->name,
            'description' => $i->description,
            'source' => $i->source,
            'amount' => (float)$i->amount,
            'currency_code' => $i->currency_code,
            'frequency' => $i->frequency,
            'frequency_interval' => $i->frequency_interval,
            'frequency_label' => $i->frequencyLabel(),
            'start_date' => $i->start_date?->toDateString(),
            'end_date' => $i->end_date?->toDateString(),
            'next_date' => $i->next_date?->toDateString(),
            'last_received_date' => $i->last_received_date?->toDateString(),
            'days_until_next' => $i->daysUntilNext(),
            'is_active' => $i->is_active,
            'is_shared' => $i->is_shared,
            'monthly_equivalent' => round($i->monthlyEquivalent(), 2),
            'notes' => $i->notes,
            'created_at' => $i->created_at?->toIso8601String(),
        ];
    }
}

