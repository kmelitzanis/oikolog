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
                alert('Παρακαλώ εισάγετε το συνολικό ποσό.');
                return;
            }
            if (this.paymentMode === 'partial') {
                if (!this.partialAmount || parseFloat(this.partialAmount) <= 0) {
                    alert('Παρακαλώ εισάγετε το ποσό που πληρώνετε τώρα.');
                    return;
                }
                const max = this.remainingBalance !== null
                    ? parseFloat(this.remainingBalance)
                    : (this.costVaries ? parseFloat(this.customAmount) : parseFloat(this.amount));
                if (parseFloat(this.partialAmount) > max) {
                    alert('Το ποσό δεν μπορεί να υπερβαίνει το υπόλοιπο (' + this.currency + ' ' + max.toFixed(2) + ').');
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
                    <div class="text-right">
                        <template x-if="remainingBalance !== null">
                            <div>
                                <div class="text-xs font-semibold text-amber-500 uppercase tracking-wide mb-0.5">
                                    Υπόλοιπο
                                </div>
                                <div class="text-base font-extrabold text-amber-600 dark:text-amber-400"
                                     x-text="currency + ' ' + parseFloat(remainingBalance).toFixed(2)"></div>
                            </div>
                        </template>
                        <template x-if="remainingBalance === null && !costVaries">
                            <div>
                                <div class="text-xs font-semibold text-gray-400 dark:text-slate-400 uppercase tracking-wide mb-0.5">
                                    Amount
                                </div>
                                <div class="text-base font-extrabold text-emerald-600 dark:text-emerald-400"
                                     x-text="currency + ' ' + amount"></div>
                            </div>
                        </template>
                        <template x-if="remainingBalance === null && costVaries">
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-indigo-500 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 px-2 py-1 rounded-lg">
                                <span class="material-icons-round text-sm">sync_alt</span> Cost varies
                            </span>
                        </template>
                    </div>
                </div>

                {{-- Custom amount when cost varies (and no prior partial) --}}
                <div x-show="costVaries && remainingBalance === null" x-cloak>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">
                        <span class="material-icons-round text-base align-middle text-gray-400 dark:text-slate-400 mr-0.5">payments</span>
                        Συνολικό ποσό περιόδου *
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-slate-400 font-semibold text-sm"
                              x-text="currencySymbol"></span>
                        <input type="number" step="0.01" min="0.01" x-model="customAmount"
                               placeholder="0.00"
                               class="w-full bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 text-gray-900 dark:text-white rounded-xl pl-6 pr-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition">
                    </div>
                </div>

                {{-- Payment mode toggle --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Τύπος
                        πληρωμής</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button"
                                @click="paymentMode = 'full'"
                                :class="paymentMode === 'full'
                                    ? 'bg-emerald-600 text-white border-emerald-600'
                                    : 'bg-white dark:bg-slate-700 text-gray-600 dark:text-slate-300 border-gray-200 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-600'"
                                class="flex items-center justify-center gap-1.5 border rounded-xl px-3 py-2.5 text-sm font-semibold transition">
                            <span class="material-icons-round text-base">check_circle</span>
                            Ολοκληρωτική
                        </button>
                        <button type="button"
                                @click="paymentMode = 'partial'"
                                :class="paymentMode === 'partial'
                                    ? 'bg-amber-500 text-white border-amber-500'
                                    : 'bg-white dark:bg-slate-700 text-gray-600 dark:text-slate-300 border-gray-200 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-600'"
                                class="flex items-center justify-center gap-1.5 border rounded-xl px-3 py-2.5 text-sm font-semibold transition">
                            <span class="material-icons-round text-base">payments</span>
                            Μερική
                        </button>
                    </div>
                </div>

                {{-- Full payment info --}}
                <div x-show="paymentMode === 'full'" x-cloak
                     class="bg-emerald-50 dark:bg-emerald-900/20 rounded-xl px-4 py-3 text-sm text-emerald-700 dark:text-emerald-300 flex items-center gap-2">
                    <span class="material-icons-round text-base shrink-0">info</span>
                    <span>
                        Θα καταγραφεί πληρωμή
                        <strong x-text="currency + ' ' + displayAmount"></strong>
                        και η επόμενη λήξη θα ενημερωθεί.
                    </span>
                </div>

                {{-- Partial payment amount --}}
                <div x-show="paymentMode === 'partial'" x-cloak>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">
                        <span class="material-icons-round text-base align-middle text-amber-500 mr-0.5">payments</span>
                        Ποσό που πληρώνετε τώρα *
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-slate-400 font-semibold text-sm"
                              x-text="currencySymbol"></span>
                        <input type="number" step="0.01" min="0.01" x-model="partialAmount"
                               placeholder="0.00"
                               class="w-full bg-gray-50 dark:bg-slate-700 border border-amber-300 dark:border-amber-600 text-gray-900 dark:text-white rounded-xl pl-6 pr-4 py-2.5 text-sm outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 dark:focus:ring-amber-900 transition">
                    </div>
                    <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                        Η ημερομηνία λήξης <strong>δεν</strong> θα αλλάξει — απλά θα μειωθεί το υπόλοιπο.
                    </p>
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
                        :class="paymentMode === 'partial'
                            ? 'bg-amber-500 hover:bg-amber-600'
                            : 'bg-emerald-600 hover:bg-emerald-700'"
                        class="flex-1 inline-flex items-center justify-center gap-2 text-white text-sm font-semibold rounded-xl px-4 py-2.5 transition">
                    <span class="material-icons-round text-lg"
                          x-text="paymentMode === 'partial' ? 'payments' : 'check_circle'"></span>
                    <span x-text="paymentMode === 'partial' ? 'Μερική Πληρωμή' : 'Επιβεβαίωση Πληρωμής'"></span>
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
        <input type="hidden" name="payment_mode" :value="paymentMode">
        <input type="hidden" name="paid_by_user_id" :value="paidByUserId">
        <input type="hidden" name="income_id" :value="incomeId">
        <input type="hidden" name="custom_amount" :value="customAmount">
        <input type="hidden" name="partial_amount" :value="partialAmount">
    </form>
</div>
