@extends('layouts.app')
@section('title', $income->name)

@section('content')
    <div class="max-w-2xl">

        {{-- Back --}}
        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('income.index') }}" class="text-gray-400 hover:text-gray-600 transition shrink-0">
                <span class="material-icons-round">arrow_back</span>
            </a>
            <h1 class="text-xl font-extrabold text-gray-900 truncate">{{ $income->name }}</h1>
            @if(!$income->is_active)
                <span
                    class="ml-auto inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">Inactive</span>
            @endif
        </div>

        @php
            $symbols = ['EUR'=>'€','USD'=>'$','GBP'=>'£','CHF'=>'Fr','CAD'=>'CA$','AUD'=>'A$','JPY'=>'¥'];
            $symbol  = $symbols[$income->currency_code] ?? $income->currency_code;
            $daysUntil = $income->daysUntilNext();
            $isRecurring = $income->frequency !== 'once';
        @endphp

        {{-- Hero card --}}
        <div class="bg-gradient-to-br from-emerald-600 to-emerald-500 rounded-2xl p-6 text-white mb-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-sm text-emerald-200 mb-1">{{ $income->source ?? 'Income' }}</div>
                    <div
                        class="text-4xl font-extrabold tracking-tight">{{ $symbol }}{{ number_format($income->amount, 2) }}</div>
                    <div class="text-sm text-emerald-300 mt-1">{{ $income->frequencyLabel() }}</div>
                    @if($isRecurring)
                        <div class="text-sm text-emerald-200 mt-2">
                            ≈ {{ $symbol }}{{ number_format($income->monthlyEquivalent(), 2) }}/mo
                            · {{ $symbol }}{{ number_format($income->monthlyEquivalent() * 12, 2) }}/yr
                        </div>
                    @endif
                </div>
                <div class="w-14 h-14 rounded-2xl bg-white/20 flex items-center justify-center shrink-0">
                    <span class="material-icons-round text-3xl">{{ $isRecurring ? 'repeat' : 'attach_money' }}</span>
                </div>
            </div>

            @if($isRecurring && $income->next_date && $income->is_active)
                <div class="mt-4 pt-4 border-t border-emerald-400/40">
                    @if($daysUntil < 0)
                        <div class="text-sm text-red-200">Expected {{ abs($daysUntil) }}
                            day{{ abs($daysUntil) !== 1 ? 's' : '' }} ago
                            — {{ $income->next_date->format('d M Y') }}</div>
                    @elseif($daysUntil === 0)
                        <div class="text-sm text-white font-semibold">Expected today!</div>
                    @else
                        <div class="text-sm text-emerald-200">Next expected in {{ $daysUntil }}
                            day{{ $daysUntil !== 1 ? 's' : '' }} — {{ $income->next_date->format('d M Y') }}</div>
                    @endif
                </div>
            @endif
        </div>

        {{-- Details card --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm divide-y divide-gray-50 mb-6">

            @foreach([
                ['label'=>'Frequency',      'value'=> $income->frequencyLabel()],
                ['label'=>'Start Date',     'value'=> $income->start_date->format('d M Y')],
                ['label'=>'End Date',       'value'=> $income->end_date ? $income->end_date->format('d M Y') : '—'],
                ['label'=>'Last Received',  'value'=> $income->last_received_date ? $income->last_received_date->format('d M Y') : 'Never'],
                ['label'=>'Status',         'value'=> $income->is_active ? 'Active' : 'Inactive'],
                ['label'=>'Shared',         'value'=> $income->is_shared ? 'Yes (Family)' : 'No'],
            ] as $row)
                <div class="flex items-center px-5 py-3.5 gap-4">
                    <span class="text-sm text-gray-400 w-32 shrink-0">{{ $row['label'] }}</span>
                    <span class="text-sm font-medium text-gray-900">{{ $row['value'] }}</span>
                </div>
            @endforeach

            @if($income->notes)
                <div class="px-5 py-3.5">
                    <div class="text-sm text-gray-400 mb-1">Notes</div>
                    <div class="text-sm text-gray-700 whitespace-pre-line">{{ $income->notes }}</div>
                </div>
            @endif
        </div>

        {{-- Actions --}}
        <div class="flex flex-wrap gap-3">
            @if($isRecurring && $income->is_active)
                <form method="POST" action="{{ route('income.receive', $income) }}">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl px-4 py-2.5 transition">
                        <span class="material-icons-round text-base">check_circle</span> Mark as Received
                    </button>
                </form>
            @endif

            <a href="{{ route('income.edit', $income) }}"
               class="inline-flex items-center gap-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-xl px-4 py-2.5 transition">
                <span class="material-icons-round text-base">edit</span> Edit
            </a>

            <form method="POST" action="{{ route('income.destroy', $income) }}" class="ml-auto" x-data>
                @csrf @method('DELETE')
                <button type="submit"
                        @click="if(!confirm('Delete {{ addslashes($income->name) }}?')) $event.preventDefault()"
                        class="inline-flex items-center gap-2 bg-red-50 hover:bg-red-100 text-red-600 text-sm font-semibold rounded-xl px-4 py-2.5 transition">
                    <span class="material-icons-round text-base">delete</span> Delete
                </button>
            </form>
        </div>

    </div>
@endsection

