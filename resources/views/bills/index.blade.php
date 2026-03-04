@extends('layouts.app')
@section('title', 'Bills')

@section('content')

    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:24px;">
        <h1 style="font-size:24px; font-weight:800; color:#0f172a;">Bills & Subscriptions</h1>
        <a href="{{ route('bills.create') }}" class="btn btn-primary">
            <span class="material-icons-round" style="font-size:18px;">add</span>
            Add Bill
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('bills.index') }}" style="display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap;">
        <input class="input" style="max-width:220px;" type="text" name="search" value="{{ request('search') }}" placeholder="Search bills...">
        <select class="input" style="max-width:160px;" name="frequency" onchange="this.form.submit()">
            <option value="">All frequencies</option>
            @foreach(['once','weekly','monthly','quarterly','yearly'] as $f)
                <option value="{{ $f }}" {{ request('frequency') === $f ? 'selected' : '' }}>{{ ucfirst($f) }}</option>
            @endforeach
        </select>
        <select class="input" style="max-width:180px;" name="status" onchange="this.form.submit()">
            <option value="">All status</option>
            <option value="active"  {{ request('status') === 'active'  ? 'selected' : '' }}>Active</option>
            <option value="overdue" {{ request('status') === 'overdue' ? 'selected' : '' }}>Overdue</option>
            <option value="inactive"{{ request('status') === 'inactive'? 'selected' : '' }}>Inactive</option>
        </select>
        <button class="btn btn-secondary" type="submit">
            <span class="material-icons-round" style="font-size:16px;">search</span> Filter
        </button>
        @if(request()->hasAny(['search','frequency','status']))
            <a href="{{ route('bills.index') }}" class="btn btn-secondary">Clear</a>
        @endif
    </form>

    {{-- Bills Table --}}
    <div class="card">
        @forelse($bills as $bill)
            @php
                $isOverdue = $bill->next_due_date && $bill->next_due_date->isPast() && $bill->is_active;
                $daysUntil = $bill->next_due_date ? (int) now()->diffInDays($bill->next_due_date, false) : 0;
                $color     = $bill->category?->color_hex ?? '#6366F1';
            @endphp
            <div style="display:flex; align-items:center; gap:14px; padding:16px 20px; {{ !$loop->last ? 'border-bottom:1px solid #f8fafc;' : '' }}">

                {{-- Icon --}}
                <div style="width:44px; height:44px; border-radius:12px; background:{{ $color }}1a; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <span class="material-icons-round" style="color:{{ $color }}; font-size:22px;">{{ $bill->category?->icon ?? 'receipt' }}</span>
                </div>

                {{-- Info --}}
                <div style="flex:1; min-width:0;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="font-size:15px; font-weight:600; color:#0f172a;">{{ $bill->name }}</span>
                        @if($bill->is_shared)
                            <span class="material-icons-round" style="font-size:14px; color:#94a3b8;" title="Shared">group</span>
                        @endif
                        @if(!$bill->is_active)
                            <span class="badge" style="background:#f1f5f9; color:#94a3b8;">Inactive</span>
                        @endif
                    </div>
                    <div style="font-size:12px; color:{{ $isOverdue ? '#ef4444' : '#94a3b8' }}; margin-top:3px;">
                        {{ $bill->category?->name }} ·
                        @if($isOverdue) Overdue by {{ abs($daysUntil) }}d
                        @elseif($daysUntil === 0) Due today
                        @else Due in {{ $daysUntil }}d @endif
                    </div>
                </div>

                {{-- Frequency badge --}}
                <div style="flex-shrink:0;">
                    <span class="badge badge-monthly">{{ ucfirst($bill->frequency) }}</span>
                </div>

                {{-- Amount --}}
                <div style="text-align:right; flex-shrink:0; min-width:110px;">
                    <div style="font-size:16px; font-weight:700; color:{{ $isOverdue ? '#ef4444' : '#0f172a' }};">
                        {{ $bill->currency_code }} {{ number_format($bill->amount, 2) }}
                    </div>
                    <div style="font-size:11px; color:#94a3b8;">{{ number_format($bill->monthlyEquivalent(), 2) }}/mo</div>
                </div>

                {{-- Actions --}}
                <div style="display:flex; align-items:center; gap:6px; flex-shrink:0;">
                    <form method="POST" action="{{ route('bills.pay', $bill) }}">
                        @csrf
                        <button type="submit" class="btn btn-secondary" style="padding:7px 12px;" title="Mark as paid">
                            <span class="material-icons-round" style="font-size:16px; color:#10b981;">check_circle</span>
                        </button>
                    </form>
                    <a href="{{ route('bills.edit', $bill) }}" class="btn btn-secondary" style="padding:7px 12px;">
                        <span class="material-icons-round" style="font-size:16px;">edit</span>
                    </a>
                    <form method="POST" action="{{ route('bills.destroy', $bill) }}" onsubmit="return confirm('Delete {{ addslashes($bill->name) }}?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger" style="padding:7px 12px;">
                            <span class="material-icons-round" style="font-size:16px;">delete</span>
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div style="text-align:center; padding:60px 20px; color:#94a3b8;">
                <span class="material-icons-round" style="font-size:56px; display:block; margin-bottom:12px;">receipt_long</span>
                <div style="font-size:16px; font-weight:600;">No bills found</div>
                <div style="font-size:13px; margin-top:4px;">
                    @if(request()->hasAny(['search','frequency','status']))
                        Try adjusting your filters
                    @else
                        <a href="{{ route('bills.create') }}" style="color:#6366f1;">Add your first bill</a>
                    @endif
                </div>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($bills->hasPages())
        <div style="margin-top:20px;">{{ $bills->appends(request()->query())->links() }}</div>
    @endif

@endsection
