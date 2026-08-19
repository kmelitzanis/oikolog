{{--
    The status chip for a bill — the visible half of `Bill::status()`.

    Label and tone are derived together here so the list and the detail page can
    never disagree about what a bill's state is called. Tones map 1:1 onto the
    ones `x-badge` already defines; do not pass a tone in.

    &lt;x-bill-status :bill="$bill" />
--}}
@props(['bill'])
@php
    $status = $bill->status();
    $days   = $bill->daysUntilDue();

    $label = match ($status) {
        'inactive' => __('messages.inactive'),
        'paid'     => __('messages.paid'),
        'partial'  => __('messages.partially_paid'),
        'overdue'  => __('messages.overdue_by', ['days' => abs($days ?? 0)]),
        'soon'     => $days === 0
            ? __('messages.due_today')
            : __('messages.in_days', ['days' => $days]),
        default    => $days !== null
            ? __('messages.in_days', ['days' => $days])
            : __('messages.upcoming'),
    };

    $icon = match ($status) {
        'paid'    => 'check_circle',
        'partial' => 'account_balance_wallet',
        'overdue' => 'error',
        'soon'    => 'schedule',
        default   => null,
    };
@endphp
<x-badge :tone="$status === 'inactive' ? 'neutral' : $status" {{ $attributes }}>
    @if($icon)
        <span class="material-icons-round" style="font-size:13px;">{{ $icon }}</span>
    @endif
    {{ $label }}
</x-badge>
