@extends('layouts.app')
@section('title', 'Income')

@section('content')

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
        <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">Income</h1>
        <a href="{{ route('income.create') }}"
           class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl px-4 py-2.5 transition">
            <span class="material-icons-round text-lg">add</span> Add Income
        </a>
    </div>
?
    {{-- Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div
            class="bg-gradient-to-br from-emerald-600 to-emerald-500 rounded-2xl p-5 text-white col-span-2 lg:col-span-1">
            <div class="flex items-center gap-1.5 text-xs text-emerald-200 font-medium mb-2">
                <span class="material-icons-round text-base">trending_up</span> Monthly Income
            </div>
            <div class="text-2xl font-extrabold leading-tight">
                {{ auth()->user()->currency_code }} {{ number_format($stats['monthly_income'], 2) }}
            </div>
            <div class="text-xs text-emerald-300 mt-1">
                {{ auth()->user()->currency_code }} {{ number_format($stats['yearly_income'], 2) }} / year
            </div>
        </div>

        @foreach([
            ['icon'=>'account_balance','color'=>'text-emerald-500','value'=>$stats['total_sources'],'label'=>'Total Sources'],
            ['icon'=>'repeat',         'color'=>'text-indigo-500', 'value'=>$stats['recurring'],    'label'=>'Recurring'],
            ['icon'=>'payments',       'color'=>'text-amber-500',  'value'=>$stats['total_sources'] - $stats['recurring'], 'label'=>'One-time'],
        ] as $s)
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm p-5 flex flex-col gap-1">
                <span class="material-icons-round {{ $s['color'] }} text-2xl">{{ $s['icon'] }}</span>
                <div class="text-2xl font-extrabold text-gray-900 dark:text-white mt-1">{{ $s['value'] }}</div>
                <div class="text-sm text-gray-400 dark:text-gray-300 font-medium">{{ $s['label'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('income.index') }}" class="flex flex-wrap gap-3 mb-6" x-data>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search income…"
               class="flex-1 min-w-40 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 dark:text-gray-100 transition">
        <select name="frequency" @change="$el.form.submit()"
                class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-emerald-500 dark:text-gray-100 transition">
            <option value="">All frequencies</option>
            @foreach(['once'=>'One-time','weekly'=>'Weekly','biweekly'=>'Bi-weekly','monthly'=>'Monthly','quarterly'=>'Quarterly','yearly'=>'Yearly'] as $fv => $fl)
                <option value="{{ $fv }}" {{ request('frequency')===$fv ? 'selected' : '' }}>{{ $fl }}</option>
            @endforeach
        </select>
        <select name="status" @change="$el.form.submit()"
                class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-emerald-500 dark:text-gray-100 transition">
            <option value="">All status</option>
            <option value="active" {{ request('status')==='active'   ? 'selected' : '' }}>Active</option>
            <option value="inactive" {{ request('status')==='inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
        <button type="submit"
                class="inline-flex items-center gap-1.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-slate-700 transition">
            <span class="material-icons-round text-base">search</span> Filter
        </button>
        @if(request()->hasAny(['search','frequency','status']))
            <a href="{{ route('income.index') }}"
               class="inline-flex items-center gap-1.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl px-4 py-2.5 text-sm text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition">Clear</a>
        @endif
    </form>

    {{-- Income List --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm overflow-hidden">
        @forelse($incomes as $income)
            @php
                $daysUntil = $income->daysUntilNext();
                $isOverdue = $daysUntil !== null && $daysUntil < 0 && $income->is_active && $income->frequency !== 'once';
                $isSoon    = !$isOverdue && $daysUntil !== null && $daysUntil <= 7 && $income->frequency !== 'once';
                $rowClass  = $isOverdue ? 'bg-red-50 dark:bg-red-900/30' : ($isSoon ? 'bg-amber-50 dark:bg-amber-900/30' : 'bg-white dark:bg-slate-800');
                $amtClass  = 'text-emerald-600 dark:text-emerald-400 font-bold';
            @endphp
            <div
                class="flex items-center gap-3 sm:gap-4 px-4 py-4 {{ !$loop->last ? 'border-b border-gray-50 dark:border-slate-700' : '' }} {{ $rowClass }} hover:brightness-95 transition"
                x-data>

                {{-- Icon --}}
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 bg-emerald-50">
                    <span class="material-icons-round text-xl text-emerald-600">
                        {{ $income->frequency === 'once' ? 'attach_money' : 'repeat' }}
                    </span>
                </div>

                {{-- Info --}}
                <div class="flex-1 min-w-0 cursor-pointer"
                     @click="window.location='{{ route('income.show', $income) }}'">
                    <div class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-1.5 truncate">
                        {{ $income->name }}
                        @if($income->is_shared)
                            <span class="material-icons-round text-gray-300" style="font-size:14px;">group</span>
                        @endif
                    </div>
                    <div
                        class="text-xs mt-0.5 {{ $isOverdue ? 'text-red-500 dark:text-red-300' : ($isSoon ? 'text-amber-500 dark:text-amber-300' : 'text-gray-400 dark:text-gray-300') }}">
                        {{ $income->source ?? 'Income' }} ·
                        @if($income->frequency === 'once')
                            {{ $income->start_date->format('d M Y') }}
                        @elseif($isOverdue)
                            Expected {{ abs($daysUntil) }}d ago
                        @elseif($daysUntil === 0)
                            Expected today
                        @elseif($daysUntil !== null)
                            In {{ $daysUntil }}d
                        @endif
                    </div>
                </div>

                {{-- Frequency badge --}}
                <span
                    class="hidden md:inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300">
                    {{ $income->frequencyLabel() }}
                </span>

                {{-- Status badge --}}
                @if(!$income->is_active)
                    <span
                        class="hidden md:inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400">Inactive</span>
                @elseif($isOverdue)
                    <span
                        class="hidden md:inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300">Late</span>
                @elseif($isSoon)
                    <span
                        class="hidden md:inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300">Soon</span>
                @endif

                {{-- Amount --}}
                <div class="text-right shrink-0 cursor-pointer"
                     @click="window.location='{{ route('income.show', $income) }}'">
                    <div
                        class="text-sm {{ $amtClass }}">{{ auth()->user()->currency_code }} {{ number_format($income->amount, 2) }}</div>
                    <div class="text-xs text-gray-400 dark:text-gray-300">{{ number_format($income->monthlyEquivalent(), 2) }}/mo</div>
                </div>

                {{-- Actions --}}
                <div class="flex items-center gap-1.5 shrink-0" @click.stop>
                    @if($income->frequency !== 'once')
                        <form method="POST" action="{{ route('income.receive', $income) }}">
                            @csrf
                            <button type="submit" title="Mark received"
                                    class="w-8 h-8 flex items-center justify-center rounded-xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-300 hover:bg-emerald-100 dark:hover:bg-emerald-800 transition">
                                <span class="material-icons-round text-base">check_circle</span>
                            </button>
                        </form>
                    @endif
                    <a href="{{ route('income.edit', $income) }}" title="Edit"
                        class="w-8 h-8 flex items-center justify-center rounded-xl bg-gray-50 dark:bg-gray-800 text-gray-500 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                        <span class="material-icons-round text-base">edit</span>
                    </a>
                    <form method="POST" action="{{ route('income.destroy', $income) }}">
                        @csrf @method('DELETE')
                        <button type="submit" title="Delete"
                                @click="if(!confirm('Delete {{ addslashes($income->name) }}?')) $event.preventDefault()"
                                class="w-8 h-8 flex items-center justify-center rounded-xl bg-red-50 dark:bg-red-900/30 text-red-400 dark:text-red-300 hover:bg-red-100 dark:hover:bg-red-800 transition">
                            <span class="material-icons-round text-base">delete</span>
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="flex flex-col items-center justify-center py-16 text-gray-400 dark:text-gray-300">
                <span class="material-icons-round text-6xl mb-3">savings</span>
                <p class="text-sm font-medium mb-4">No income sources yet</p>
                <a href="{{ route('income.create') }}"
                   class="inline-flex items-center gap-2 bg-emerald-600 text-white text-sm font-semibold rounded-xl px-4 py-2.5 hover:bg-emerald-700 dark:bg-emerald-700 dark:hover:bg-emerald-800 transition">
                    <span class="material-icons-round text-lg">add</span> Add your first income
                </a>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($incomes->hasPages())
        <div class="mt-4">{{ $incomes->links() }}</div>
    @endif

@endsection

