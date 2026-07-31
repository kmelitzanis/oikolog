{{--
    Flash toast — the mockup's floating pill (2a / 3a), replacing the inline
    alert banners that used to push page content down.

    Sits above the mobile tab bar and bottom-right on desktop. Auto-dismisses
    after a few seconds; errors stay until dismissed, since losing an error
    message is worse than a lingering pill.

    An optional `undo_route` in the session renders the mockup's Undo action:

        return back()->with('success', __('messages.payment_recorded'))
                     ->with('undo_route', route('bills.unpay', $bill));
--}}
@php
    $toastMessage = session('success') ?? session('error');
    $toastIsError = (bool) session('error');
    $toastUndo    = session('undo_route');
@endphp

@if($toastMessage)
    <div x-data="{ show: false }"
         x-init="$nextTick(() => show = true); {{ $toastIsError ? '' : 'setTimeout(() => show = false, 5000)' }}"
         x-show="show" x-cloak
         x-transition:enter="transition ease-out duration-250"
         x-transition:enter-start="opacity-0 translate-y-2.5"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         role="status" aria-live="polite"
         class="fixed z-[90] left-4 right-4 bottom-[120px] lg:left-auto lg:right-6 lg:bottom-6 lg:w-[380px]
                flex items-center gap-[11px] rounded-[18px] px-[15px] py-[13px]
                bg-white dark:bg-slate-900 shadow-[0_14px_34px_rgba(2,6,23,0.6)]
                {{ $toastIsError ? 'border border-red-500/35' : 'border border-emerald-500/35' }}">
        <span class="w-[30px] h-[30px] rounded-[10px] shrink-0 flex items-center justify-center
                     {{ $toastIsError ? 'bg-red-500/[0.18] text-red-500' : 'bg-emerald-500/[0.18] text-emerald-500' }}">
            <span class="material-icons-round text-base">{{ $toastIsError ? 'error' : 'check' }}</span>
        </span>
        <span class="flex-1 min-w-0 text-[0.84rem] font-semibold text-gray-900 dark:text-white">{{ $toastMessage }}</span>
        @if($toastUndo)
            <form method="POST" action="{{ $toastUndo }}" class="shrink-0">
                @csrf @method('DELETE')
                <button type="submit" class="text-[0.76rem] font-bold text-amber-600 dark:text-amber-400 hover:underline">
                    {{ __('messages.undo') }}
                </button>
            </form>
        @else
            <button type="button" @click="show = false"
                    class="shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-slate-200 transition flex">
                <span class="material-icons-round text-base">close</span>
            </button>
        @endif
    </div>
@endif
