{{--
    Global "Mark as Paid" confirmation modal.
    Triggered by dispatching a custom event from anywhere on the page:

    $dispatch('open-pay-modal', {
        billName: '...',
        amount:   '123.00',
        currency: 'EUR',
        payRoute: '/bills/{id}/pay'
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
        paidByUserId: '{{ auth()->id() }}',
        incomeId: '',

        currencySymbols: {'EUR':'€','USD':'$','GBP':'£','CHF':'Fr','CAD':'CA$','AUD':'A$','JPY':'¥'},
        get currencySymbol() { return this.currencySymbols[this.currency] ?? this.currency; },

        openModal(data) {
            this.billName     = data.billName        ?? '';
            this.amount       = data.amount          ?? '';
            this.currency     = data.currency        ?? '';
            this.payRoute     = data.payRoute        ?? '';
            this.costVaries   = data.costVaries      ?? false;
            this.customAmount = data.lastPaidAmount  ?? '';
            this.paidByUserId = '{{ auth()->id() }}';
            this.incomeId     = '';
            this.open         = true;
        },

        submit() {
            if (!this.payRoute) return;
            if (this.costVaries && (!this.customAmount || parseFloat(this.customAmount) <= 0)) {
                alert('Παρακαλώ εισάγετε το ποσό πληρωμής.');
                return;
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
        class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-end sm:items-center justify-center p-4"
        @click.self="open = false"
    >
        {{-- Modal Panel --}}
        <div
            x-show="open" x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-gray-100 dark:border-slate-700 overflow-hidden"
        >
            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <div class="flex items-center gap-2.5">
                    <div
                        class="w-9 h-9 bg-emerald-100 dark:bg-emerald-900/40 rounded-xl flex items-center justify-center">
                        <span
                            class="material-icons-round text-emerald-600 dark:text-emerald-400 text-xl">check_circle</span>
                    </div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">{{ __('messages.mark_paid') }}</h3>
                </div>
                <button type="button" @click="open = false"
                        class="p-1.5 rounded-lg text-gray-400 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 transition">
                    <span class="material-icons-round text-lg">close</span>
                </button>
            </div>

            {{-- Body --}}
            <div class="px-5 py-4 space-y-4">

                {{-- Bill Summary --}}
                <div class="flex items-center justify-between bg-gray-50 dark:bg-slate-700/50 rounded-xl px-4 py-3">
                    <div>
                        <div
                            class="text-xs font-semibold text-gray-400 dark:text-slate-400 uppercase tracking-wide mb-0.5">
                            Bill
                        </div>
                        <div class="text-sm font-semibold text-gray-900 dark:text-white" x-text="billName"></div>
                    </div>
                    <div class="text-right" x-show="!costVaries">
                        <div
                            class="text-xs font-semibold text-gray-400 dark:text-slate-400 uppercase tracking-wide mb-0.5">
                            Amount
                        </div>
                        <div class="text-base font-extrabold text-emerald-600 dark:text-emerald-400"
                             x-text="currency + ' ' + amount"></div>
                    </div>
                    <div x-show="costVaries" x-cloak>
                        <span
                            class="inline-flex items-center gap-1 text-xs font-semibold text-indigo-500 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 px-2 py-1 rounded-lg">
                            <span class="material-icons-round text-sm">sync_alt</span> Cost varies
                        </span>
                    </div>
                </div>

                {{-- Custom amount when cost varies --}}
                <div x-show="costVaries" x-cloak>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">
                        <span
                            class="material-icons-round text-base align-middle text-gray-400 dark:text-slate-400 mr-0.5">payments</span>
                        Ποσό πληρωμής *
                    </label>
                    <div class="relative">
                        <span
                            class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-slate-400 font-semibold text-sm"
                            x-text="currencySymbol"></span>
                        <input type="number" step="0.01" min="0.01" x-model="customAmount"
                               :required="costVaries"
                               placeholder="0.00"
                               class="w-full bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 text-gray-900 dark:text-white rounded-xl pl-6 pr-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition">
                    </div>
                </div>

                {{-- Who Pays (only if family with multiple members) --}}
                @if($familyMembers->count() > 1)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">
                            <span
                                class="material-icons-round text-base align-middle text-gray-400 dark:text-slate-400 mr-0.5">person</span>
                            Ποιος πληρώνει;
                        </label>
                        <select x-model="paidByUserId"
                                class="w-full bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 text-gray-900 dark:text-white rounded-xl px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition">
                            @foreach($familyMembers as $member)
                                <option value="{{ $member->id }}">
                                    {{ $member->name }}{{ $member->id === auth()->id() ? ' (εγώ)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                {{-- Deduct from income account --}}
                @if($userIncomes->count() > 0)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">
                            <span
                                class="material-icons-round text-base align-middle text-gray-400 dark:text-slate-400 mr-0.5">account_balance</span>
                            Αφαίρεση από λογαριασμό εσόδων
                            <span class="text-gray-400 dark:text-slate-500 font-normal text-xs">(προαιρετικό)</span>
                        </label>
                        <select x-model="incomeId"
                                class="w-full bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 text-gray-900 dark:text-white rounded-xl px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition">
                            <option value="">— Κανένας —</option>
                            @foreach($userIncomes as $income)
                                <option value="{{ $income->id }}">
                                    {{ $income->name }}
                                    ({{ $income->currency_code }} {{ number_format($income->amount, 2) }})
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-400 dark:text-slate-500">Συνδέει αυτή την πληρωμή με έναν
                            λογαριασμό εσόδων.</p>
                    </div>
                @endif

            </div>

            {{-- Footer --}}
            <div
                class="px-5 py-4 bg-gray-50 dark:bg-slate-700/30 border-t border-gray-100 dark:border-slate-700 flex items-center gap-3">
                <button type="button" @click="submit()"
                        class="flex-1 inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl px-4 py-2.5 transition">
                    <span class="material-icons-round text-lg">check_circle</span>
                    Επιβεβαίωση Πληρωμής
                </button>
                <button type="button" @click="open = false"
                        class="inline-flex items-center gap-2 bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 text-gray-600 dark:text-slate-300 text-sm font-medium rounded-xl px-4 py-2.5 hover:bg-gray-50 dark:hover:bg-slate-600 transition">
                    Ακύρωση
                </button>
            </div>
        </div>
    </div>

    {{-- Hidden form submitted programmatically --}}
    <form method="POST" x-ref="payForm" style="display:none">
        @csrf
        <input type="hidden" name="paid_by_user_id" :value="paidByUserId">
        <input type="hidden" name="income_id" :value="incomeId">
        <input type="hidden" name="custom_amount" :value="customAmount">
    </form>
</div>

