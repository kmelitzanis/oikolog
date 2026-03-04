@extends('layouts.app')
@section('title', $bill->name)

@section('content')

    <div style="max-width:720px;">

        {{-- Back + Actions --}}
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:24px;">
            <div style="display:flex; align-items:center; gap:12px;">
                <a href="{{ route('bills.index') }}" style="color:#94a3b8; display:flex;">
                    <span class="material-icons-round">arrow_back</span>
                </a>
                <h1 style="font-size:22px; font-weight:800; color:#0f172a;">{{ $bill->name }}</h1>
                @if(!$bill->is_active)
                    <span class="badge" style="background:#f1f5f9; color:#94a3b8;">Inactive</span>
                @endif
            </div>
            <div style="display:flex; gap:8px;">
                <a href="{{ route('bills.edit', $bill) }}" class="btn btn-secondary">
                    <span class="material-icons-round" style="font-size:16px;">edit</span> Edit
                </a>
                <form method="POST" action="{{ route('bills.destroy', $bill) }}" onsubmit="return confirm('Delete this bill?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger" type="submit">
                        <span class="material-icons-round" style="font-size:16px;">delete</span>
                    </button>
                </form>
            </div>
        </div>

        {{-- Header Card --}}
        @php
            $isOverdue = $bill->next_due_date && $bill->next_due_date->isPast() && $bill->is_active;
            $daysUntil = $bill->next_due_date ? (int) now()->diffInDays($bill->next_due_date, false) : 0;
            $color     = $bill->category?->color_hex ?? '#6366F1';
        @endphp
        <div class="card" style="padding:24px; margin-bottom:16px;">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:20px;">
                <div style="background:{{ $isOverdue ? '#fee2e2' : '#ede9fe' }}; border-radius:12px; padding:16px;">
                    <div style="font-size:11px; font-weight:600; color:{{ $isOverdue ? '#dc2626' : '#6366f1' }}; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">Amount</div>
                    <div style="font-size:26px; font-weight:800; color:{{ $isOverdue ? '#dc2626' : '#0f172a' }};">{{ $bill->currency_code }} {{ number_format($bill->amount, 2) }}</div>
                </div>
                <div style="background:#f0fdf4; border-radius:12px; padding:16px;">
                    <div style="font-size:11px; font-weight:600; color:#059669; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">Monthly Equiv.</div>
                    <div style="font-size:26px; font-weight:800; color:#0f172a;">{{ $bill->currency_code }} {{ number_format($bill->monthlyEquivalent(), 2) }}</div>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div style="background:#f8fafc; border-radius:12px; padding:14px;">
                    <div style="font-size:11px; color:#94a3b8; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">Next Due</div>
                    <div style="font-size:16px; font-weight:700; color:{{ $isOverdue ? '#ef4444' : '#0f172a' }};">
                        {{ $bill->next_due_date->format('d M Y') }}
                    </div>
                    <div style="font-size:12px; color:{{ $isOverdue ? '#ef4444' : '#94a3b8' }}; margin-top:2px;">
                        @if($isOverdue) Overdue by {{ abs($daysUntil) }}d
                        @elseif($daysUntil === 0) Today
                        @else In {{ $daysUntil }} days @endif
                    </div>
                </div>
                <div style="background:#f8fafc; border-radius:12px; padding:14px;">
                    <div style="font-size:11px; color:#94a3b8; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">Last Paid</div>
                    <div style="font-size:16px; font-weight:700; color:#0f172a;">
                        {{ $bill->last_paid_date ? $bill->last_paid_date->format('d M Y') : 'Never' }}
                    </div>
                </div>
            </div>

            @if($bill->is_active)
                <form method="POST" action="{{ route('bills.pay', $bill) }}" style="margin-top:16px;">
                    @csrf
                    <button class="btn btn-primary" type="submit" style="width:100%; justify-content:center; background:#10b981; padding:14px;">
                        <span class="material-icons-round" style="font-size:18px;">check_circle</span>
                        Mark as Paid
                    </button>
                </form>
            @endif
        </div>

        {{-- Details --}}
        <div class="card" style="padding:24px; margin-bottom:16px;">
            <h2 style="font-size:16px; font-weight:700; color:#0f172a; margin-bottom:16px;">Details</h2>
            @php
                $details = [
                    'Category'    => $bill->category?->name ?? '—',
                    'Frequency'   => ucfirst($bill->frequency),
                    'Start Date'  => $bill->start_date->format('d M Y'),
                    'End Date'    => $bill->end_date ? $bill->end_date->format('d M Y') : '—',
                    'Shared'      => $bill->is_shared ? 'Yes, with family' : 'No',
                    'Reminder'    => $bill->notify_enabled ? $bill->notify_days_before.' days before due' : 'Off',
                ];
            @endphp
            @foreach($details as $key => $val)
                <div style="display:flex; padding:10px 0; {{ !$loop->last ? 'border-bottom:1px solid #f8fafc;' : '' }}">
                    <span style="width:130px; font-size:13px; color:#94a3b8; font-weight:500; flex-shrink:0;">{{ $key }}</span>
                    <span style="font-size:13px; color:#0f172a; font-weight:500;">{{ $val }}</span>
                </div>
            @endforeach
            @if($bill->url)
                <div style="display:flex; padding:10px 0; border-top:1px solid #f8fafc;">
                    <span style="width:130px; font-size:13px; color:#94a3b8; font-weight:500; flex-shrink:0;">Website</span>
                    <a href="{{ $bill->url }}" target="_blank" style="font-size:13px; color:#6366f1; font-weight:500;">{{ $bill->url }}</a>
                </div>
            @endif
            @if($bill->notes)
                <div style="margin-top:14px; background:#f8fafc; border-radius:10px; padding:12px 14px;">
                    <div style="font-size:11px; color:#94a3b8; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">Notes</div>
                    <div style="font-size:13px; color:#475569;">{{ $bill->notes }}</div>
                </div>
            @endif
        </div>

        {{-- Payment History --}}
        <div class="card" style="padding:24px;">
            <h2 style="font-size:16px; font-weight:700; color:#0f172a; margin-bottom:16px;">Payment History</h2>
            @forelse($payments as $payment)
                <div style="display:flex; align-items:center; gap:12px; padding:10px 0; {{ !$loop->last ? 'border-bottom:1px solid #f8fafc;' : '' }}">
                    <div style="width:34px; height:34px; background:#d1fae5; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <span class="material-icons-round" style="color:#059669; font-size:16px;">check</span>
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:14px; font-weight:600; color:#0f172a;">
                            {{ $payment->currency_code }} {{ number_format($payment->amount, 2) }}
                        </div>
                        <div style="font-size:12px; color:#94a3b8;">
                            {{ $payment->paid_at->format('d M Y, H:i') }} · {{ $payment->paidBy?->name ?? '—' }}
                        </div>
                    </div>
                    @if($payment->notes)
                        <div style="font-size:12px; color:#94a3b8; max-width:160px; text-align:right;">{{ $payment->notes }}</div>
                    @endif
                </div>
            @empty
                <p style="font-size:13px; color:#94a3b8; text-align:center; padding:20px 0;">No payments recorded yet.</p>
            @endforelse
        </div>

    </div>
@endsection
