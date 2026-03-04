@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')

    {{-- Header --}}
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:28px;">
        <div>
            <h1 style="font-size:24px; font-weight:800; color:#0f172a;">
                Hello, {{ explode(' ', auth()->user()->name)[0] }} 👋
            </h1>
            <p style="font-size:14px; color:#94a3b8; margin-top:2px;">{{ now()->format('l, F j, Y') }}</p>
        </div>
        <a href="{{ route('bills.create') }}" class="btn btn-primary">
            <span class="material-icons-round" style="font-size:18px;">add</span>
            Add Bill
        </a>
    </div>

    {{-- Stats Row --}}
    <div style="display:grid; grid-template-columns:2fr 1fr 1fr 1fr; gap:16px; margin-bottom:24px;">

        {{-- Monthly Total --}}
        <div style="background:linear-gradient(135deg,#6366f1,#4338ca); border-radius:16px; padding:24px; color:#fff;">
            <div style="font-size:13px; color:rgba(255,255,255,0.7); font-weight:500; margin-bottom:8px; display:flex; align-items:center; gap:6px;">
                <span class="material-icons-round" style="font-size:16px;">account_balance_wallet</span>
                Monthly Total
            </div>
            <div style="font-size:38px; font-weight:800; letter-spacing:-1px; line-height:1;">
                {{ auth()->user()->currency_code }} {{ number_format($stats['monthly_total'], 2) }}
            </div>
            <div style="font-size:12px; color:rgba(255,255,255,0.6); margin-top:8px;">
                {{ auth()->user()->currency_code }} {{ number_format($stats['yearly_total'], 2) }} per year
            </div>
        </div>

        {{-- Active --}}
        <div class="card" style="padding:20px;">
            <span class="material-icons-round" style="color:#6366f1; font-size:24px;">receipt_long</span>
            <div style="font-size:32px; font-weight:800; color:#0f172a; margin:8px 0 4px;">{{ $stats['active_count'] }}</div>
            <div style="font-size:13px; color:#94a3b8; font-weight:500;">Active Bills</div>
        </div>

        {{-- Due This Week --}}
        <div class="card" style="padding:20px;">
            <span class="material-icons-round" style="color:#f59e0b; font-size:24px;">schedule</span>
            <div style="font-size:32px; font-weight:800; color:#0f172a; margin:8px 0 4px;">{{ $stats['due_this_week'] }}</div>
            <div style="font-size:13px; color:#94a3b8; font-weight:500;">Due This Week</div>
        </div>

        {{-- Overdue --}}
        <div class="card" style="padding:20px;">
            <span class="material-icons-round" style="color:#ef4444; font-size:24px;">warning</span>
            <div style="font-size:32px; font-weight:800; color:{{ $stats['overdue_count'] > 0 ? '#ef4444' : '#0f172a' }}; margin:8px 0 4px;">{{ $stats['overdue_count'] }}</div>
            <div style="font-size:13px; color:#94a3b8; font-weight:500;">Overdue</div>
        </div>
    </div>

    {{-- Main Content --}}
    <div style="display:grid; grid-template-columns:1fr 320px; gap:20px;">

        {{-- Upcoming Bills --}}
        <div class="card" style="padding:24px;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
                <h2 style="font-size:17px; font-weight:700; color:#0f172a;">Upcoming Bills</h2>
                <a href="{{ route('bills.index') }}" style="font-size:13px; color:#6366f1; font-weight:600; text-decoration:none;">See all →</a>
            </div>

            @forelse($upcoming as $bill)
                @php
                    $isOverdue  = $bill->next_due_date && $bill->next_due_date->isPast();
                    $daysUntil  = $bill->next_due_date ? (int) now()->diffInDays($bill->next_due_date, false) : 0;
                    $color      = $bill->category?->color_hex ?? '#6366F1';
                @endphp
                <div style="display:flex; align-items:center; gap:14px; padding:12px 0; {{ !$loop->last ? 'border-bottom:1px solid #f8fafc;' : '' }}">
                    <div style="width:42px; height:42px; border-radius:12px; background:{{ $color }}1a; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <span class="material-icons-round" style="color:{{ $color }}; font-size:20px;">{{ $bill->category?->icon ?? 'receipt' }}</span>
                    </div>
                    <div style="flex:1; min-width:0;">
                        <div style="font-size:14px; font-weight:600; color:#0f172a; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $bill->name }}</div>
                        <div style="font-size:12px; color:{{ $isOverdue ? '#ef4444' : '#94a3b8' }}; margin-top:2px;">
                            @if($isOverdue)
                                Overdue by {{ abs($daysUntil) }} day{{ abs($daysUntil) !== 1 ? 's' : '' }}
                            @elseif($daysUntil === 0)
                                Due today
                            @else
                                In {{ $daysUntil }} day{{ $daysUntil !== 1 ? 's' : '' }}
                            @endif
                        </div>
                    </div>
                    <div style="text-align:right; flex-shrink:0;">
                        <div style="font-size:15px; font-weight:700; color:{{ $isOverdue ? '#ef4444' : '#0f172a' }};">
                            {{ $bill->currency_code }} {{ number_format($bill->amount, 2) }}
                        </div>
                        <form method="POST" action="{{ route('bills.pay', $bill) }}" style="margin-top:4px;">
                            @csrf
                            <button type="submit" style="background:none; border:none; font-size:12px; color:#10b981; font-weight:600; cursor:pointer; font-family:'Inter',sans-serif; padding:0;">
                                ✓ Mark paid
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div style="text-align:center; padding:32px 0; color:#94a3b8;">
                    <span class="material-icons-round" style="font-size:40px; display:block; margin-bottom:8px;">celebration</span>
                    No upcoming bills — you're all caught up!
                </div>
            @endforelse
        </div>

        {{-- Spend by Category --}}
        <div class="card" style="padding:24px;">
            <h2 style="font-size:17px; font-weight:700; color:#0f172a; margin-bottom:20px;">By Category</h2>

            @php $total = $byCategory->sum(); @endphp
            @forelse($byCategory->take(10) as $name => $amount)
                @php $pct = $total > 0 ? ($amount / $total * 100) : 0; @endphp
                <div style="margin-bottom:14px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                        <span style="font-size:13px; color:#475569; font-weight:500;">{{ $name }}</span>
                        <span style="font-size:13px; color:#0f172a; font-weight:600;">{{ number_format($amount, 2) }}</span>
                    </div>
                    <div style="background:#f1f5f9; border-radius:4px; height:5px; overflow:hidden;">
                        <div style="background:#6366f1; height:100%; width:{{ $pct }}%; border-radius:4px;"></div>
                    </div>
                </div>
            @empty
                <p style="font-size:13px; color:#94a3b8; text-align:center; padding:24px 0;">No bills yet.</p>
            @endforelse
        </div>
    </div>

@endsection
