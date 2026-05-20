<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Payment;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BillController extends Controller
{
    public function index(Request $request)
    {
        $user  = $request->user();
        $query = Bill::with(['category', 'provider', 'payments' => function ($q) {
            $q->latest('paid_at')->with('paidBy');
        }])
            ->forUser($user)
            ->orderBy('next_due_date');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('frequency')) {
            $query->where('frequency', $request->frequency);
        }
        if ($request->filled('status')) {
            match ($request->status) {
                'active'   => $query->where('is_active', true),
                'overdue'  => $query->where('is_active', true)->whereDate('next_due_date', '<', now()),
                'inactive' => $query->where('is_active', false),
                default    => null,
            };
        }

        $bills = $query->paginate(50);

        return view('bills.index', compact('bills'));
    }

    public function create()
    {
        $categories = Category::orderBy('name')->get();
        $providers = Provider::with('categories')->orderBy('name')->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'logo_url' => $p->logo_url,
                'category_ids' => $p->categories->pluck('id')->all(),
            ]);

        return view('bills.form', compact('categories', 'providers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'               => ['required', 'string', 'max:120'],
            'description'        => ['nullable', 'string'],
            'category_id'        => ['required', 'exists:categories,id'],
            'provider_id' => ['nullable', 'exists:providers,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'cost_varies' => ['nullable', 'boolean'],
            'frequency'          => ['required', 'in:once,daily,weekly,biweekly,monthly,quarterly,yearly'],
            'start_date'         => ['required', 'date'],
            'end_date'           => ['nullable', 'date', 'after:start_date'],
            'is_shared'          => ['nullable'],
            'notify_enabled'     => ['nullable'],
            'notify_days_before' => ['nullable', 'integer', 'min:1', 'max:30'],
            'url'                => ['nullable', 'url'],
            'notes'              => ['nullable', 'string'],
        ]);

        $bill = Bill::create([
            ...$data,
            'currency_code' => $request->user()->currency_code,
            'cost_varies' => (bool)($data['cost_varies'] ?? false),
            'is_shared'      => (bool) ($data['is_shared'] ?? false),
            'notify_enabled' => (bool) ($data['notify_enabled'] ?? false),
            'created_by'     => $request->user()->id,
            'family_id'      => ($data['is_shared'] ?? false) ? $request->user()->family_id : null,
            'next_due_date'  => $data['start_date'],
        ]);

        // Handle uploaded receipt images (optional, via Spatie medialibrary)
        if ($request->hasFile('receipts') && class_exists(\Spatie\MediaLibrary\MediaCollections\Models\Media::class) && method_exists($bill, 'addMedia')) {
            foreach ($request->file('receipts') as $file) {
                try {
                    $bill->addMedia($file->getRealPath())->usingFileName(uniqid() . '.' . $file->getClientOriginalExtension())->toMediaCollection('receipts');
                } catch (\Exception $e) {
                    // ignore individual failures
                }
            }
        }

        return redirect()->route('bills.show', $bill)->with('success', 'Bill created.');
    }

    public function show(Bill $bill)
    {
        $this->authorizeView($bill);
        $bill->load(['category', 'provider', 'payments.paidBy', 'payments.income']);
        $payments = $bill->payments()->with(['paidBy', 'income'])->orderByDesc('paid_at')->get();

        return view('bills.show', compact('bill', 'payments'));
    }

    // Calendar view
    public function calendar()
    {
        return view('calendar.index');
    }

    // Events for FullCalendar — returns bills + incomes
    public function events(Request $request)
    {
        $user = $request->user();

        // Parse dates - handle both RFC 3339 with timezone and ISO 8601 formats
        $startStr = $request->get('start');
        $endStr = $request->get('end');

        // Remove space before timezone offset if present (URL encoding issue)
        if ($startStr && strpos($startStr, ' ') !== false) {
            $startStr = str_replace(' ', '+', $startStr);
        }
        if ($endStr && strpos($endStr, ' ') !== false) {
            $endStr = str_replace(' ', '+', $endStr);
        }

        $start = $startStr ? \Carbon\Carbon::parse($startStr) : now()->startOfMonth();
        $end = $endStr ? \Carbon\Carbon::parse($endStr) : now()->endOfMonth();

        // ── Bills ─────────────────────────────────────────────────────────────
        $bills = Bill::forUser($user)->whereNotNull('next_due_date')
            ->with(['category', 'provider', 'payments' => function ($q) use ($start, $end) {
                $q->whereBetween('paid_at', [$start->startOfDay(), $end->endOfDay()]);
            }])->get();

        $billEvents = collect();

        foreach ($bills as $b) {
            // Get all occurrences between start and end
            $occurrences = $b->occurrencesBetween($start, $end);

            foreach ($occurrences as $date) {
                // Check if this occurrence was paid
                $isPaid = $b->payments->some(fn($p) => $p->paid_at->toDateString() === $date->toDateString());

                $isOverdue = $date->isPast() && !$isPaid && $b->is_active;
                $isSoon = !$isOverdue && !$isPaid && $date->diffInDays(now(), false) <= 7 && $date->isFuture() && $b->is_active;

                // Determine color
                if ($isPaid) {
                    $color = '#10b981'; // Green for paid
                } elseif ($isOverdue) {
                    $color = '#ef4444'; // Red for overdue
                } elseif ($isSoon) {
                    $color = '#f97316'; // Orange for upcoming soon
                } else {
                    $color = $b->category?->color_hex ?? '#6366f1';
                }

                $billEvents->push([
                    'id' => 'bill-' . $b->id . '-' . $date->timestamp,
                    'title' => '• ' . $b->name,
                    'start' => $date->toDateString(),
                    'allDay' => true,
                    'url' => route('bills.show', $b),
                    'color' => $color,
                    'extendedProps' => [
                        'type' => 'bill',
                        'amount' => $b->currency_code . ' ' . number_format($b->amount, 2),
                        'overdue' => $isOverdue,
                        'paid' => $isPaid,
                        'soon' => $isSoon,
                        'provider' => $b->provider?->name ?? '',
                    ],
                ]);
            }
        }

        // ── Incomes ───────────────────────────────────────────────────────────
        $incomes = \App\Models\Income::forUser($user)->active()->whereNotNull('next_date')->get();

        $incomeEvents = $incomes->map(function ($i) {
            return [
                'id' => 'income-' . $i->id,
                'title' => '• ' . $i->name,
                'start' => $i->next_date?->toDateString(),
                'allDay' => true,
                'url' => route('income.show', $i),
                'color' => '#10b981',
                'extendedProps' => [
                    'type' => 'income',
                    'amount' => $i->currency_code . ' ' . number_format($i->amount, 2),
                ],
            ];
        });

        return response()->json($billEvents->concat($incomeEvents)->values());
    }

    public function edit(Bill $bill)
    {
        $this->authorizeEdit($bill);
        $categories = Category::orderBy('name')->get();
        $providers = Provider::with('categories')->orderBy('name')->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'logo_url' => $p->logo_url,
                'category_ids' => $p->categories->pluck('id')->all(),
            ]);

        return view('bills.form', compact('bill', 'categories', 'providers'));
    }

    public function update(Request $request, Bill $bill)
    {
        $this->authorizeEdit($bill);

        $data = $request->validate([
            'name'               => ['required', 'string', 'max:120'],
            'description'        => ['nullable', 'string'],
            'category_id'        => ['required', 'exists:categories,id'],
            'provider_id' => ['nullable', 'exists:providers,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'cost_varies' => ['nullable', 'boolean'],
            'frequency'          => ['required', 'in:once,daily,weekly,biweekly,monthly,quarterly,yearly'],
            'start_date'         => ['required', 'date'],
            'end_date'           => ['nullable', 'date'],
            'is_shared'          => ['nullable'],
            'notify_enabled'     => ['nullable'],
            'notify_days_before' => ['nullable', 'integer', 'min:1', 'max:30'],
            'url'                => ['nullable', 'url'],
            'notes'              => ['nullable', 'string'],
        ]);

        $data['cost_varies'] = (bool)($data['cost_varies'] ?? false);
        $data['is_shared']      = (bool) ($data['is_shared'] ?? false);
        $data['notify_enabled'] = (bool) ($data['notify_enabled'] ?? false);

        if (isset($data['is_shared'])) {
            $data['family_id'] = $data['is_shared'] ? $request->user()->family_id : null;
        }

        $bill->update($data);

        // Handle uploaded receipt images on update
        if ($request->hasFile('receipts') && class_exists(\Spatie\MediaLibrary\MediaCollections\Models\Media::class) && method_exists($bill, 'addMedia')) {
            foreach ($request->file('receipts') as $file) {
                try {
                    $bill->addMedia($file->getRealPath())->usingFileName(uniqid() . '.' . $file->getClientOriginalExtension())->toMediaCollection('receipts');
                } catch (\Exception $e) {
                    // ignore
                }
            }
        }

        return redirect()->route('bills.show', $bill)->with('success', 'Bill updated.');
    }

    public function destroy(Bill $bill)
    {
        $this->authorizeEdit($bill);
        $bill->delete();

        return redirect()->route('bills.index')->with('success', 'Bill deleted.');
    }

    public function markPaid(Request $request, Bill $bill)
    {
        $this->authorizeView($bill);

        $paidByUserId = $request->input('paid_by_user_id', $request->user()->id);
        $incomeId = $request->input('income_id') ?: null;

        // Use custom amount when cost_varies is enabled, otherwise use bill's default amount
        $amount = $bill->cost_varies && $request->filled('custom_amount')
            ? (float)$request->input('custom_amount')
            : (float)$bill->amount;

        DB::transaction(function () use ($bill, $request, $paidByUserId, $incomeId, $amount) {
            Payment::create([
                'bill_id'       => $bill->id,
                'paid_by' => $paidByUserId,
                'income_id' => $incomeId,
                'amount' => $amount,
                'currency_code' => $bill->currency_code,
                'paid_at'       => now(),
            ]);

            $nextDue = $bill->calculateNextDueDate();
            $bill->update([
                'last_paid_date' => now()->toDateString(),
                'next_due_date'  => $nextDue?->toDateString() ?? $bill->next_due_date,
            ]);
        });

        if ($request->wantsJson() || $request->ajax()) {
            $bill->refresh();
            return response()->json([
                'status' => 'paid',
                'last_paid_date' => $bill->last_paid_date?->toDateString(),
                'next_due_date' => $bill->next_due_date?->toDateString(),
                'message' => 'Payment recorded.',
            ]);
        }

        return back()->with('success', 'Payment recorded.');
    }

    public function undoLastPayment(Bill $bill)
    {
        $this->authorizeView($bill);

        $lastPayment = $bill->payments()->latest('paid_at')->first();

        if (!$lastPayment) {
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json(['status' => 'none', 'message' => 'No payment to undo.'], 422);
            }
            return back()->with('error', 'No payment found to undo.');
        }

        DB::transaction(function () use ($bill, $lastPayment) {
            $paidAt = $lastPayment->paid_at; // store
            $lastPayment->delete();

            // Previous payment becomes last_paid_date
            $prevPayment = $bill->payments()->latest('paid_at')->first();
            $bill->update([
                'last_paid_date' => $prevPayment?->paid_at?->toDateString(),
                'next_due_date' => $paidAt?->toDateString(),
            ]);
        });

        $bill->refresh();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'status' => 'undone',
                'last_paid_date' => $bill->last_paid_date?->toDateString(),
                'next_due_date' => $bill->next_due_date?->toDateString(),
                'message' => 'Payment undone successfully.',
            ]);
        }

        return back()->with('success', 'Payment undone successfully.');
    }

    private function authorizeView(Bill $bill): void
    {
        $user = request()->user();
        $ok   = $user instanceof \App\Models\User
             && ($bill->created_by === $user->id
             || ($bill->is_shared && $bill->family_id === $user->family_id));
        abort_unless($ok, 403, 'Access denied.');
    }

    private function authorizeEdit(Bill $bill): void
    {
        $user = request()->user();
        $ok   = $user instanceof \App\Models\User
             && ($bill->created_by === $user->id || $user->isFamilyAdmin());
        abort_unless($ok, 403, 'You cannot edit this bill.');
    }
}

