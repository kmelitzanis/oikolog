{{--
    Global "Mark as Paid" confirmation modal.
    Triggered by dispatching a custom event from anywhere on the page:

    $dispatch('open-pay-modal', {
        billName: '...',
        amount:   '123.00',
        currency: 'EUR',
        payRoute: '/bills/{id}/pay',
        costVaries: false,
        remainingBalance: null   // pass if there's an outstanding partial balance
    })
--}}
@php
    $familyMembers = collect();
    $userIncomes   = collect();

    if (auth()->check()) {
        $authUser = auth()->user();

        if ($authUser->family_id) {
            $familyMembers = \App\Models\User::where('family_id', $authUser->family_id)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        $userIncomes = \App\Models\Income::forUser($authUser)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'amount', 'currency_code']);
    }
@endphp

<div
    x-data="{
        open: false,
        billName: '',
        amount: '',
        currency: '',
        payRoute: '',
        costVaries: false,
        customAmount: '',
        partialAmount: '',
        paidByUserId: '{{ auth()->id() }}',
        incomeId: '',
        paymentMode: 'full',
        remainingBalance: null,

        currencySymbols: {'EUR':'€','USD':'$','GBP':'£','CHF':'Fr','CAD':'CA$','AUD':'A$','JPY':'¥'},
        get currencySymbol() { return this.currencySymbols[this.currency] ?? this.currency; },

        get displayAmount() {
            if (this.remainingBalance !== null) return parseFloat(this.remainingBalance).toFixed(2);
            if (!this.costVaries) return this.amount;
            return this.customAmount || '—';
        },

        openModal(data) {
            this.billName        = data.billName         ?? '';
            this.amount          = data.amount           ?? '';
            this.currency        = data.currency         ?? '';
            this.payRoute        = data.payRoute         ?? '';
            this.costVaries      = data.costVaries       ?? false;
            this.customAmount    = data.lastPaidAmount   ?? '';
            this.remainingBalance = data.remainingBalance ?? null;
            this.partialAmount   = '';
            this.paidByUserId    = '{{ auth()->id() }}';
            this.incomeId        = data.defaultIncomeId ?? '';
            this.paymentMode     = 'full';
            this.open            = true;
        },

        submit() {
            if (!this.payRoute) return;

            if (this.costVaries && !this.remainingBalance && (!this.customAmount || parseFloat(this.customAmount) <= 0)) {
                alert(@js(__('messages.enter_total_amount')));
                return;
            }
            if (this.paymentMode === 'partial') {
                if (!this.partialAmount || parseFloat(this.partialAmount) <= 0) {
                    alert(@js(__('messages.enter_partial_amount')));
                    return;
                }
                const max = this.remainingBalance !== null
                    ? parseFloat(this.remainingBalance)
                    : (this.costVaries ? parseFloat(this.customAmount) : parseFloat(this.amount));
                if (parseFloat(this.partialAmount) > max) {
                    alert(@js(__('messages.amount_exceeds_balance')).replace(':max', this.currency + ' ' + max.toFixed(2)));
                    return;
                }
            }

            this.$refs.payForm.action = this.payRoute;
            this.$refs.payForm.submit();
        }
    }"
    @open-pay-modal.window="openModal($event.detail)"
>
    {{-- Backdrop --}}
    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[60] bg-slate-950/[0.68] backdrop-blur-sm flex items-end sm:items-center justify-center p-4"
        @click.self="open = false"
    >
        {{-- Modal panel — the mockup's 460px / 24px-radius sheet. The app keeps
             controls the mockup has no equivalent for (partial payments, payer,
             income link); they follow the same visual language. --}}
        <div
            x-show="open" x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="w-full max-w-[460px] max-h-[90vh] overflow-y-auto bg-white dark:bg-slate-800 rounded-[24px] p-6 border border-gray-100 dark:border-slate-700 shadow-[0_30px_70px_rgba(2,6,23,0.6)]"
        >
            {{-- Header --}}
            <div class="flex items-center gap-[13px] mb-5">
                <div class="w-11 h-11 rounded-2xl shrink-0 flex items-center justify-center bg-emerald-500/[0.14] text-emerald-600 dark:text-emerald-400">
                    <span class="material-icons-round text-xl">check_circle</span>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-[1.05rem] font-bold text-gray-900 dark:text-white truncate" x-text="billName"></div>
                    <div class="text-[0.76rem] text-gray-400 dark:text-slate-500 mt-px">{{ __('messages.mark_paid') }}</div>
                </div>
                <button type="button" @click="open = false"
                        class="w-8 h-8 rounded-xl shrink-0 bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-slate-300 flex items-center justify-center hover:bg-gray-200 dark:hover:bg-slate-600 transition">
                    <span class="material-icons-round text-base">close</span>
                </button>
            </div>

            {{-- Amount — the mockup's inset panel. It's the editable field when
                 the cost varies or a partial amount is being entered, and a
                 read-only figure otherwise. --}}
            <div class="bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-2xl p-4 mb-3">
                <div class="text-[0.62rem] font-bold uppercase tracking-[0.09em] text-gray-400 dark:text-slate-500"
                     x-text="paymentMode === 'partial'
                        ? '{{ __('messages.partial_amount_label') }}'
                        : '{{ __('messages.payment_amount_label') }}'"></div>
                <div class="flex items-center gap-2 mt-1.5">
                    <template x-if="paymentMode === 'partial'">
                        <input type="number" step="0.01" min="0.01" x-model="partialAmount" placeholder="0.00"
                               class="flex-1 min-w-0 bg-transparent border-0 outline-none p-0 text-[2rem] font-extrabold tracking-[-0.03em] text-gray-900 dark:text-white">
                    </template>
                    <template x-if="paymentMode !== 'partial' && costVaries && remainingBalance === null">
                        <input type="number" step="0.01" min="0.01" x-model="customAmount" placeholder="0.00"
                               class="flex-1 min-w-0 bg-transparent border-0 outline-none p-0 text-[2rem] font-extrabold tracking-[-0.03em] text-gray-900 dark:text-white">
                    </template>
                    <template x-if="paymentMode !== 'partial' && !(costVaries && remainingBalance === null)">
                        <div class="flex-1 min-w-0 text-[2rem] font-extrabold tracking-[-0.03em] text-gray-900 dark:text-white truncate"
                             x-text="displayAmount"></div>
                    </template>
                    <span class="text-[1.4rem] font-extrabold text-gray-400 dark:text-slate-600 shrink-0"
                          x-text="currencySymbol"></span>
                </div>
                <template x-if="remainingBalance !== null">
                    <div class="text-[0.72rem] font-semibold text-amber-600 dark:text-amber-400 mt-1.5">
                        {{ __('messages.outstanding_balance') }}
                    </div>
                </template>
            </div>

            {{-- Date · from income --}}
            <div class="grid grid-cols-2 gap-2.5 mb-3.5">
                <div class="bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-2xl px-3.5 py-[11px]">
                    <div class="text-[0.68rem] text-gray-400 dark:text-slate-500">{{ __('messages.date') }}</div>
                    <div class="text-[0.84rem] font-semibold text-gray-700 dark:text-slate-200 mt-0.5">
                        {{ now()->translatedFormat('j M Y') }}
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-2xl px-3.5 py-[11px] min-w-0">
                    <div class="text-[0.68rem] text-gray-400 dark:text-slate-500">{{ __('messages.from_income') }}</div>
                    @if($userIncomes->count() > 0)
                        <select x-model="incomeId"
                                class="w-full bg-transparent border-0 outline-none p-0 mt-0.5 text-[0.84rem] font-semibold text-gray-700 dark:text-slate-200">
                            <option value="">—</option>
                            @foreach($userIncomes as $income)
                                <option value="{{ $income->id }}">{{ $income->name }}</option>
                            @endforeach
                        </select>
                    @else
                        <div class="text-[0.84rem] font-semibold text-gray-400 dark:text-slate-500 mt-0.5">—</div>
                    @endif
                </div>
            </div>

            {{-- Payment mode --}}
            <div class="grid grid-cols-2 gap-2.5 mb-3.5">
                <button type="button" @click="paymentMode = 'full'"
                        :class="paymentMode === 'full'
                            ? 'bg-emerald-500/[0.14] border-emerald-500/40 text-emerald-700 dark:text-emerald-400'
                            : 'bg-transparent border-gray-200 dark:border-slate-700 text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700'"
                        class="flex items-center justify-center gap-1.5 border rounded-2xl px-3 py-2.5 text-[0.82rem] font-semibold transition">
                    <span class="material-icons-round text-base">check_circle</span>{{ __('messages.pay_full') }}
                </button>
                <button type="button" @click="paymentMode = 'partial'"
                        :class="paymentMode === 'partial'
                            ? 'bg-amber-500/[0.16] border-amber-500/40 text-amber-700 dark:text-amber-300'
                            : 'bg-transparent border-gray-200 dark:border-slate-700 text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-700'"
                        class="flex items-center justify-center gap-1.5 border rounded-2xl px-3 py-2.5 text-[0.82rem] font-semibold transition">
                    <span class="material-icons-round text-base">payments</span>{{ __('messages.pay_partial') }}
                </button>
            </div>

            <p x-show="paymentMode === 'partial'" x-cloak
               class="text-[0.72rem] text-amber-600 dark:text-amber-400 mb-3.5">
                {{ __('messages.partial_hint') }}
            </p>

            {{-- Who pays — only with more than one family member --}}
            @if($familyMembers->count() > 1)
                <div class="bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-2xl px-3.5 py-[11px] mb-3.5">
                    <div class="text-[0.68rem] text-gray-400 dark:text-slate-500">{{ __('messages.who_pays') }}</div>
                    <select x-model="paidByUserId"
                            class="w-full bg-transparent border-0 outline-none p-0 mt-0.5 text-[0.84rem] font-semibold text-gray-700 dark:text-slate-200">
                        @foreach($familyMembers as $member)
                            <option value="{{ $member->id }}">
                                {{ $member->name }}{{ $member->id === auth()->id() ? ' (' . __('messages.me') . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            {{-- Footer --}}
            <div class="flex gap-2.5">
                <button type="button" @click="open = false"
                        class="shrink-0 h-12 px-[18px] rounded-2xl border border-gray-200 dark:border-slate-700 bg-transparent text-gray-500 dark:text-slate-400 text-[0.88rem] font-semibold transition hover:bg-gray-50 dark:hover:bg-slate-700">
                    {{ __('messages.cancel') }}
                </button>
                <button type="button" @click="submit()"
                        class="flex-1 h-12 rounded-2xl bg-amber-500 hover:bg-amber-400 text-slate-900 text-[0.92rem] font-bold flex items-center justify-center gap-2.5 transition shadow-[0_8px_22px_rgba(245,158,11,0.4)]">
                    <span class="material-icons-round text-lg">check</span>
                    {{ __('messages.record_payment') }}
                </button>
            </div>
        </div>
    </div>

    {{-- Hidden form submitted programmatically --}}
    <form method="POST" x-ref="payForm" style="display:none">
        @csrf
        <input type="hidden" name="payment_mode" :value="paymentMode">
        <input type="hidden" name="paid_by_user_id" :value="paidByUserId">
        <input type="hidden" name="income_id" :value="incomeId">
        <input type="hidden" name="custom_amount" :value="customAmount">
        <input type="hidden" name="partial_amount" :value="partialAmount">
    </form>
</div>
