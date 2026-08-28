{{--
    A figure the invoice-mail crawler parsed out of a provider's email, waiting
    for a person to accept it.

    Nothing writes a crawled amount to a bill without going through here — the
    excerpt is shown precisely so a wrong match is obvious before it is taken.

    &lt;x-amount-suggestion :bill="$bill" :suggestion="$s" />
--}}
@props(['bill', 'suggestion'])
<div class="rounded-2xl border border-sky-300/60 dark:border-sky-500/30 bg-sky-50 dark:bg-sky-500/10 p-4">
    <div class="flex items-start gap-3">
        <div class="w-9 h-9 rounded-xl bg-sky-500/15 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
            <span class="material-icons-round text-lg">mark_email_read</span>
        </div>

        <div class="flex-1 min-w-0">
            <div class="text-sm font-bold text-gray-900 dark:text-white">
                {{ __('messages.suggested_amount', [
                    'amount' => $bill->currency_code . ' ' . number_format($suggestion->amount, 2),
                ]) }}
            </div>
            <div class="text-xs text-gray-500 dark:text-slate-400 mt-0.5 truncate">
                {{ $suggestion->from_address }}
                @if($suggestion->email_date) · {{ $suggestion->email_date->translatedFormat('j M Y') }} @endif
            </div>
            @if($suggestion->subject)
                <div class="text-xs text-gray-500 dark:text-slate-400 truncate">{{ $suggestion->subject }}</div>
            @endif
            @if($suggestion->excerpt)
                {{-- The matched fragment: this is what makes a bad regex visible. --}}
                <div class="mt-2 text-[0.7rem] leading-relaxed text-gray-500 dark:text-slate-400
                            bg-white/70 dark:bg-slate-900/40 rounded-lg px-2.5 py-1.5 border border-sky-200/70 dark:border-sky-500/20">
                    …{{ $suggestion->excerpt }}…
                </div>
            @endif

            <div class="flex gap-2 mt-3">
                <form method="POST" action="{{ route('bills.suggestions.accept', [$bill, $suggestion]) }}">
                    @csrf
                    <x-btn variant="success" size="sm" type="submit" icon="check">
                        {{ __('messages.accept_amount') }}
                    </x-btn>
                </form>
                <form method="POST" action="{{ route('bills.suggestions.reject', [$bill, $suggestion]) }}">
                    @csrf
                    <x-btn variant="ghost" size="sm" type="submit" icon="close">
                        {{ __('messages.dismiss') }}
                    </x-btn>
                </form>
            </div>
        </div>
    </div>
</div>
