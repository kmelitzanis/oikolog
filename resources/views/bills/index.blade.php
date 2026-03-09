@extends('layouts.app')
@section('title', 'Bills')

@section('content')

    <div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
        <h1 class="text-2xl font-extrabold text-gray-900">Bills & Subscriptions</h1>
        <a href="{{ route('bills.create') }}"
           class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl px-4 py-2.5 transition">
            <span class="material-icons-round text-lg">add</span> Add Bill
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('bills.index') }}"
          class="flex flex-wrap gap-3 mb-6" x-data>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search bills…"
               class="flex-1 min-w-40 bg-white border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition">
        <select name="frequency" @change="$el.form.submit()"
                class="bg-white border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-indigo-500 transition">
            <option value="">All frequencies</option>
            @foreach(['once','weekly','monthly','quarterly','yearly'] as $f)
                <option value="{{ $f }}" {{ request('frequency')===$f ? 'selected' : '' }}>{{ ucfirst($f) }}</option>
            @endforeach
        </select>
        <select name="status" @change="$el.form.submit()"
                class="bg-white border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-indigo-500 transition">
            <option value="">All status</option>
            <option value="active" {{ request('status')==='active'   ? 'selected' : '' }}>Active</option>
            <option value="overdue" {{ request('status')==='overdue'  ? 'selected' : '' }}>Overdue</option>
            <option value="inactive" {{ request('status')==='inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
        <button type="submit"
                class="inline-flex items-center gap-1.5 bg-white border border-gray-200 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
            <span class="material-icons-round text-base">search</span> Filter
        </button>
        @if(request()->hasAny(['search','frequency','status']))
            <a href="{{ route('bills.index') }}"
               class="inline-flex items-center gap-1.5 bg-white border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-500 hover:bg-gray-50 transition">
                Clear
            </a>
        @endif
    </form>

    {{-- Bills --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        @forelse($bills as $bill)
            @php
                $isOverdue  = $bill->next_due_date && $bill->next_due_date->isPast() && $bill->is_active;
                $daysUntil  = $bill->next_due_date ? (int) now()->diffInDays($bill->next_due_date, false) : null;
                $isSoon     = !$isOverdue && $daysUntil !== null && $daysUntil <= 7 && $bill->is_active;
                $lastPayment = $bill->payments->first();
                $isPaid     = (bool)$bill->last_paid_date;
                $color      = $bill->category?->color_hex ?? '#6366F1';
                $rowClass   = $isOverdue ? 'bg-red-50' : ($isSoon ? 'bg-orange-50' : ($isPaid ? 'bg-green-50' : 'bg-white'));
                $amountClass = $isOverdue ? 'text-red-600 font-bold' : ($isSoon ? 'text-orange-600 font-bold' : ($isPaid ? 'text-emerald-600 font-bold' : 'text-gray-900 font-bold'));
            @endphp
            <div x-data="{ paid: {{ $lastPayment ? 'true' : 'false' }} }"
                 class="flex items-center gap-3 sm:gap-4 px-4 py-4 {{ !$loop->last ? 'border-b border-gray-50' : '' }} {{ $rowClass }} hover:brightness-95 transition cursor-pointer"
                 @click.self="window.location='{{ route('bills.show', $bill) }}'">

                {{-- Category icon --}}
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                     style="background:{{ $color }}1a;" @click="window.location='{{ route('bills.show', $bill) }}'">
                    <span class="material-icons-round text-xl"
                          style="color:{{ $color }}">{{ $bill->category?->icon ?? 'receipt' }}</span>
                </div>

                {{-- Info --}}
                <div class="flex-1 min-w-0" @click="window.location='{{ route('bills.show', $bill) }}'">
                    <div class="text-sm font-semibold text-gray-900 flex items-center gap-1.5 truncate">
                        {{ $bill->name }}
                        @if($bill->is_shared)
                            <span class="material-icons-round text-gray-300" style="font-size:14px;">group</span>
                        @endif
                    </div>
                    <div
                        class="text-xs mt-0.5 {{ $isOverdue ? 'text-red-500' : ($isSoon ? 'text-orange-500' : 'text-gray-400') }}">
                        {{ $bill->category?->name ?? '—' }} ·
                        @if($isOverdue)
                            Overdue {{ abs($daysUntil) }}d
                        @elseif($daysUntil === 0)
                            Due today
                        @elseif($daysUntil !== null)
                            In {{ $daysUntil }}d
                        @else
                            —
                        @endif
                    </div>
                </div>

                {{-- Badges (hidden on mobile) --}}
                <span
                    class="hidden md:inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700">
                {{ ucfirst($bill->frequency) }}
            </span>

                @if($isOverdue)
                    <span
                        class="hidden md:inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">Overdue</span>
                @elseif($isSoon)
                    <span
                        class="hidden md:inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-orange-100 text-orange-700">Soon</span>
                @elseif($isPaid)
                    <span
                        class="hidden md:inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">Paid</span>
                @else
                    <span
                        class="hidden md:inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">Active</span>
                @endif

                {{-- Amount --}}
                <div class="text-right shrink-0" @click="window.location='{{ route('bills.show', $bill) }}'">
                    <div
                        class="text-sm {{ $amountClass }}">{{ $bill->currency_code }} {{ number_format($bill->amount, 2) }}</div>
                    <div class="text-xs text-gray-400">{{ number_format($bill->monthlyEquivalent(), 2) }}/mo</div>
                </div>

                {{-- Actions --}}
                <div class="flex items-center gap-1.5 shrink-0" @click.stop>

                    {{-- Pay --}}
                    <form method="POST" action="{{ route('bills.pay', $bill) }}" x-show="!paid">
                        @csrf
                        <button type="submit" title="Mark paid"
                                class="w-8 h-8 flex items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 hover:bg-emerald-100 transition">
                            <span class="material-icons-round text-base">check_circle</span>
                        </button>
                    </form>

                    {{-- Unpay --}}
                    <form method="POST" action="{{ route('bills.unpay', $bill) }}" x-show="paid"
                          x-cloak>
                        @csrf @method('DELETE')
                        <button type="submit" title="Undo payment"
                                @click="if(!confirm('Undo this payment?')) $event.preventDefault()"
                                class="w-8 h-8 flex items-center justify-center rounded-xl bg-orange-50 text-orange-500 hover:bg-orange-100 transition">
                            <span class="material-icons-round text-base">undo</span>
                        </button>
                    </form>

                    <a href="{{ route('bills.edit', $bill) }}" title="Edit"
                       class="w-8 h-8 flex items-center justify-center rounded-xl bg-gray-50 text-gray-500 hover:bg-gray-100 transition">
                        <span class="material-icons-round text-base">edit</span>
                    </a>

                    <form method="POST" action="{{ route('bills.destroy', $bill) }}">
                        @csrf @method('DELETE')
                        <button type="submit" title="Delete"
                                @click="if(!confirm('Delete {{ addslashes($bill->name) }}?')) $event.preventDefault()"
                                class="w-8 h-8 flex items-center justify-center rounded-xl bg-gray-50 text-red-400 hover:bg-red-50 hover:text-red-600 transition">
                            <span class="material-icons-round text-base">delete</span>
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="flex flex-col items-center justify-center py-16 text-gray-400">
                <span class="material-icons-round text-6xl mb-3">receipt_long</span>
                <div class="text-base font-semibold">No bills found</div>
                <div class="text-sm mt-1">
                    @if(request()->hasAny(['search','frequency','status']))
                        Try adjusting your filters
                    @else
                        <a href="{{ route('bills.create') }}" class="text-indigo-600 hover:underline">Add your first
                            bill</a>
                    @endif
                </div>
            </div>
        @endforelse
    </div>

    @if($bills->hasPages())
        <div class="mt-6">{{ $bills->appends(request()->query())->links() }}</div>
    @endif

@endsection

