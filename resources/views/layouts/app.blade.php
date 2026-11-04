<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('messages.dashboard')) — Oikolog</title>
    @include('partials.pwa')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    {{-- Prevent dark-mode flash before JS loads --}}
    <script>
        (function () {
            // Storage can throw (private mode, blocked cookies); the theme
            // then simply follows the system.
            var t = null;
            try { t = localStorage.getItem('theme'); } catch (e) {}
            if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    @include('partials.assets')
    @stack('head')
</head>
<body class="bg-gray-50 dark:bg-slate-900 font-sans antialiased">
<div x-data="{
        sidebarOpen: false,
        userMenuOpen: false,
        mobileUserOpen: false,
        isDark: document.documentElement.classList.contains('dark'),
        toggleTheme() {
            this.isDark = !this.isDark;
            document.documentElement.classList.toggle('dark', this.isDark);
            try { localStorage.setItem('theme', this.isDark ? 'dark' : 'light'); } catch (e) {}
        }
     }"
     @keydown.escape.window="sidebarOpen = false; userMenuOpen = false; mobileUserOpen = false"
     class="min-h-screen flex">
    {{-- Mobile backdrop --}}
    <div x-show="sidebarOpen" @click="sidebarOpen=false" x-cloak
         class="fixed inset-0 bg-black/40 z-[44] lg:hidden"></div>
    {{-- ── Sidebar ───────────────────────────────────────────────── --}}
    <aside :class="sidebarOpen ? 'translate-x-0' : ''"
           class="fixed inset-y-0 left-0 z-[45] w-64 bg-white dark:bg-slate-800 border-r border-gray-100 dark:border-slate-700 flex flex-col transform transition-transform duration-200 ease-in-out -translate-x-full lg:translate-x-0">
        {{-- Brand --}}
        <div class="flex items-center px-5 py-5 border-b border-gray-100 dark:border-slate-700">
            <a href="{{ route('dashboard') }}"><x-logo /></a>
        </div>
        {{-- Nav --}}
        <nav class="flex-1 px-3 py-5 space-y-1 overflow-y-auto">
            @php
                $navLinks = [
                    ['route' => 'dashboard',         'icon' => 'dashboard',    'label' => __('messages.dashboard'),    'match' => 'dashboard'],
                    ['route' => 'bills.index',       'icon' => 'receipt_long', 'label' => __('messages.bills'),        'match' => 'bills.*', 'count' => $overdueBillCount ?? 0],
                    ['route' => 'income.index',      'icon' => 'trending_up',  'label' => __('messages.income'),       'match' => ['income.*', 'accounts.*']],
                    ['route' => 'expenses.index',    'icon' => 'shopping_bag', 'label' => __('messages.expenses_menu'), 'match' => 'expenses.*'],
                    ['route' => 'vehicles.index',    'icon' => 'directions_car', 'label' => __('messages.vehicles'),   'match' => 'vehicles.*'],
                    ['route' => 'family.index',      'icon' => 'group',        'label' => __('messages.family'),       'match' => 'family.*'],
                    ['route' => 'shopping-list.index', 'icon' => 'shopping_cart', 'label' => __('messages.shopping_lists'), 'match' => ['shopping-list.*', 'products.*']],
                    ['route' => 'recipes.index',     'icon' => 'restaurant_menu','label' => __('messages.recipes'),      'match' => 'recipes.*'],
                ];
            @endphp
            @foreach($navLinks as $link)
                <a href="{{ route($link['route']) }}" @click="sidebarOpen=false"
                   @if(request()->routeIs(...(array) $link['match'])) aria-current="page" @endif
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors
                          {{ request()->routeIs(...(array) $link['match'])
                              ? 'bg-amber-50 dark:bg-amber-500/15 text-amber-700 dark:text-amber-400'
                              : 'text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-white' }}">
                    <span class="material-icons-round text-xl">{{ $link['icon'] }}</span>
                    <span>{{ $link['label'] }}</span>
                    {{-- Overdue counter. `ms-auto` pins it to the trailing edge
                         so it survives a right-to-left locale. --}}
                    @if(($link['count'] ?? 0) > 0)
                        @php $badgeLabel = trans_choice('messages.overdue_count', $link['count'], ['count' => $link['count']]); @endphp
                        <span class="ms-auto min-w-5 h-5 px-1.5 inline-flex items-center justify-center
                                     rounded-full bg-red-500 text-white text-[0.66rem] font-bold leading-none"
                              title="{{ $badgeLabel }}" aria-label="{{ $badgeLabel }}">
                            {{ $link['count'] > 99 ? '99+' : $link['count'] }}
                        </span>
                    @endif
                </a>
            @endforeach
            @if(auth()->check() && auth()->user()->isAdmin())
                <div class="pt-3 mt-2 border-t border-gray-100 dark:border-slate-700">
                    <p class="px-3 pb-1.5 text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">{{ __('messages.admin') }}</p>
                    @foreach([
                        ['route'=>'admin.categories.index','icon'=>'category',        'label'=>__('messages.categories'),'match'=>'admin.categories.*'],
                        ['route'=>'admin.providers.index', 'icon'=>'business',        'label'=>__('messages.providers'),  'match'=>'admin.providers.*'],
                        ['route'=>'admin.users.index',     'icon'=>'manage_accounts', 'label'=>__('messages.users'),      'match'=>'admin.users.*'],
                        ['route'=>'translations.index',    'icon'=>'translate',       'label'=>__('messages.translations'), 'match'=>'translations.*'],
                    ] as $al)
                        <a href="{{ route($al['route']) }}" @click="sidebarOpen=false"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors
                                  {{ request()->routeIs($al['match'])
                                      ? 'bg-amber-50 dark:bg-amber-500/15 text-amber-700 dark:text-amber-400'
                                      : 'text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-white' }}">
                            <span class="material-icons-round text-xl">{{ $al['icon'] }}</span>
                            <span>{{ $al['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </nav>
        {{-- User footer --}}
        <div class="px-3 py-4 border-t border-gray-100 dark:border-slate-700">
            <button type="button"
                class="relative w-full text-left flex items-center gap-3 p-2 rounded-xl hover:bg-gray-50 dark:hover:bg-slate-700 transition cursor-pointer"
                @click="userMenuOpen=!userMenuOpen" :aria-expanded="userMenuOpen ? 'true' : 'false'" aria-haspopup="menu">
                <div
                    class="w-9 h-9 rounded-xl bg-amber-500 flex items-center justify-center text-slate-900 font-bold text-sm shrink-0 overflow-hidden">
                    @if(auth()->user()?->avatar_url)
                        <img src="{{ auth()->user()->avatar_url }}" class="w-full h-full object-cover" alt="">
                    @else
                        {{ mb_strtoupper(mb_substr(auth()->user()?->name ?? '?', 0, 1)) }}
                    @endif
                </div>
                <div class="flex-1 min-w-0">
                    <div
                        class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ auth()->user()?->name }}</div>
                    <div class="text-xs text-gray-400 dark:text-slate-500">{{ auth()->user()?->currency_code }}</div>
                </div>
                <span class="material-icons-round text-gray-400 dark:text-slate-500 text-lg transition-transform"
                      :class="userMenuOpen ? 'rotate-180' : ''" aria-hidden="true">expand_more</span>
            </button>
            <div class="mt-2 text-center text-[0.65rem] text-gray-300 dark:text-slate-600 select-all">
                v{{ config('app.version') }}
            </div>
            {{-- User dropdown --}}
            <div x-show="userMenuOpen" @click.outside="userMenuOpen=false" x-cloak
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-75"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="absolute bottom-20 left-3 right-3 bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-2xl shadow-lg overflow-hidden z-50">
                {{-- Settings --}}
                <a href="{{ route('settings') }}"
                   class="flex items-center gap-3 px-4 py-3 text-sm text-gray-700 dark:text-slate-200 hover:bg-gray-50 dark:hover:bg-slate-700 transition">
                    <span class="material-icons-round text-gray-400 dark:text-slate-400 text-lg">settings</span>
                    {{ __('messages.settings') }}
                </a>
                {{-- Theme toggle --}}
                <button type="button" @click="toggleTheme()"
                        class="w-full flex items-center gap-3 px-4 py-3 text-sm text-gray-700 dark:text-slate-200 hover:bg-gray-50 dark:hover:bg-slate-700 transition border-t border-gray-100 dark:border-slate-700">
                    <span class="material-icons-round text-gray-400 dark:text-slate-400 text-lg"
                          x-text="isDark ? 'light_mode' : 'dark_mode'"></span>
                    <span x-text="isDark ? @js(__('messages.light')) : @js(__('messages.dark'))"></span>
                </button>
                {{-- Language switcher --}}
                <div class="flex border-t border-gray-100 dark:border-slate-700">
                    @php $langLabels = ['en'=>'EN','el'=>'ΕΛ']; @endphp
                    @foreach($availableLocales ?? ['en'] as $loc)
                        <a href="{{ route('locale.set', $loc) }}"
                           class="flex-1 text-center py-2.5 text-xs font-bold transition {{ app()->getLocale()===$loc ? 'text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-500/15' : 'text-gray-400 dark:text-slate-500 hover:text-gray-700 dark:hover:text-slate-300' }}">
                            {{ $langLabels[$loc] ?? strtoupper($loc) }}
                        </a>
                    @endforeach
                </div>
                {{-- Logout --}}
                <form method="POST" action="{{ route('logout') }}"
                      class="border-t border-gray-100 dark:border-slate-700">
                    @csrf
                    <button type="submit"
                            class="w-full text-left px-4 py-3 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition flex items-center gap-3">
                        <span class="material-icons-round text-lg">logout</span>
                        {{ __('messages.logout') }}
                    </button>
                </form>
            </div>
        </div>
    </aside>
    {{-- ── Mobile topbar ─────────────────────────────────────────── --}}
    <div
        class="lg:hidden fixed inset-x-0 top-0 z-30 bg-white dark:bg-slate-800 border-b border-gray-100 dark:border-slate-700"
        style="padding-top: env(safe-area-inset-top);">
        <div class="flex items-center justify-between px-4 h-14">
            <div class="flex items-center gap-3">
                <button type="button" @click="sidebarOpen=true" aria-label="{{ __('messages.menu') }}"
                        :aria-expanded="sidebarOpen ? 'true' : 'false'"
                        class="p-2 -ml-2 rounded-xl text-gray-600 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700 transition">
                    <span class="material-icons-round">menu</span>
                </button>
                <a href="{{ route('dashboard') }}"><x-logo /></a>
            </div>
            {{-- Profile menu. The sidebar's version sits in the drawer footer,
                 which is off-screen on a phone until the drawer is opened, so
                 the account actions get their own entry point here. --}}
            @auth
                <div class="relative">
                    <button type="button" @click="mobileUserOpen = !mobileUserOpen"
                            :aria-expanded="mobileUserOpen ? 'true' : 'false'"
                            aria-label="{{ auth()->user()?->name }}"
                            class="w-9 h-9 rounded-xl bg-amber-500 flex items-center justify-center text-slate-900 font-bold text-sm overflow-hidden">
                        @if(auth()->user()?->avatar_url)
                            <img src="{{ auth()->user()->avatar_url }}" class="w-full h-full object-cover" alt="">
                        @else
                            {{ mb_strtoupper(mb_substr(auth()->user()?->name ?? '?', 0, 1)) }}
                        @endif
                    </button>

                    <div x-show="mobileUserOpen" @click.outside="mobileUserOpen = false" x-cloak
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 top-12 w-56 origin-top-right bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-2xl shadow-lg overflow-hidden z-[46]">
                        <div class="px-4 py-3 border-b border-gray-100 dark:border-slate-700">
                            <div class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ auth()->user()?->name }}</div>
                            <div class="text-xs text-gray-400 dark:text-slate-500">{{ auth()->user()?->currency_code }}</div>
                        </div>
                        <a href="{{ route('settings') }}"
                           class="flex items-center gap-3 px-4 py-3 text-sm text-gray-700 dark:text-slate-200 hover:bg-gray-50 dark:hover:bg-slate-700 transition">
                            <span class="material-icons-round text-gray-400 dark:text-slate-400 text-lg">settings</span>
                            {{ __('messages.settings') }}
                        </a>
                        <button type="button" @click="toggleTheme()"
                                class="w-full flex items-center gap-3 px-4 py-3 text-sm text-gray-700 dark:text-slate-200 hover:bg-gray-50 dark:hover:bg-slate-700 transition border-t border-gray-100 dark:border-slate-700">
                            <span class="material-icons-round text-gray-400 dark:text-slate-400 text-lg"
                                  x-text="isDark ? 'light_mode' : 'dark_mode'"></span>
                            <span x-text="isDark ? @js(__('messages.light')) : @js(__('messages.dark'))"></span>
                        </button>
                        <div class="flex border-t border-gray-100 dark:border-slate-700">
                            @foreach($availableLocales ?? ['en'] as $loc)
                                <a href="{{ route('locale.set', $loc) }}"
                                   class="flex-1 text-center py-2.5 text-xs font-bold transition {{ app()->getLocale()===$loc ? 'text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-500/15' : 'text-gray-400 dark:text-slate-500' }}">
                                    {{ ['en'=>'EN','el'=>'ΕΛ'][$loc] ?? strtoupper($loc) }}
                                </a>
                            @endforeach
                        </div>
                        <form method="POST" action="{{ route('logout') }}" class="border-t border-gray-100 dark:border-slate-700">
                            @csrf
                            <button type="submit"
                                    class="w-full text-left px-4 py-3 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition flex items-center gap-3">
                                <span class="material-icons-round text-lg">logout</span>
                                {{ __('messages.logout') }}
                            </button>
                        </form>
                    </div>
                </div>
            @endauth
        </div>
    </div>
    {{-- ── Page content ──────────────────────────────────────────── --}}
    <div class="flex-1 lg:pl-64 min-w-0">
        <main class="min-h-screen px-4 sm:px-6 lg:px-8 pt-[calc(5rem+env(safe-area-inset-top))] lg:pt-8 pb-[calc(7rem+env(safe-area-inset-bottom))] lg:pb-12 max-w-7xl mx-auto">
            {{-- success / error now surface as the mockup's floating toast,
                 rendered near the end of <body>. Validation errors stay inline:
                 they belong beside the form that produced them. --}}
            @if($errors->any())
                <div
                    class="flex items-start gap-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 rounded-2xl px-4 py-3 text-sm mb-6">
                    <span class="material-icons-round text-red-500 text-lg mt-0.5">error</span>
                    <div>
                        <div class="font-semibold mb-1">{{ __('messages.whoops') }}</div>
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach($errors->all() as $e)
                                <li>{{ $e }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
    {{-- ── Mobile "+" sheet ─────────────────────────────────────────────
         Four things done on the go, each finished from here: a purchase, a
         bill paid, income that came in, a fill-up. Paying and receiving open
         a short list of what is due, so a tap acts on the right one instead
         of landing on a page to look for it. Adding a bill or a source is
         rarer and lives on those pages' own buttons. --}}
    @php
        $qa = $quickActions ?? ['bills' => collect(), 'incomes' => collect(), 'vehicles' => collect()];
        $sheetCard = 'rounded-[19px] border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800';
        $sheetRow = 'flex items-center gap-[13px] p-4 w-full text-left transition active:scale-[0.98]';
    @endphp
    <div x-data="{ fabOpen: false, panel: null }"
         @keydown.escape.window="fabOpen = false; panel = null" class="lg:hidden">
        <div x-show="fabOpen" x-cloak x-transition.opacity.duration.180ms
             class="fixed inset-0 z-[41] bg-slate-950/[0.72] backdrop-blur-md"
             @click="fabOpen = false; panel = null"></div>

        <div x-show="fabOpen" x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-2.5"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="fixed left-4 right-4 bottom-[calc(118px+env(safe-area-inset-bottom))] z-[42] flex flex-col gap-[9px] max-h-[calc(100dvh-140px-env(safe-area-inset-bottom)-env(safe-area-inset-top))] overflow-y-auto">

            {{-- New purchase --}}
            <a href="{{ route('expenses.create') }}" class="{{ $sheetCard }} {{ $sheetRow }}">
                <span class="w-10 h-10 rounded-[13px] shrink-0 flex items-center justify-center bg-amber-500/[0.18] text-amber-500 dark:text-amber-300">
                    <span class="material-icons-round text-lg">shopping_bag</span>
                </span>
                <span class="flex-1 min-w-0">
                    <span class="block text-[0.9rem] font-bold text-gray-900 dark:text-white">{{ __('messages.expense_add') }}</span>
                    <span class="block text-[0.72rem] text-gray-400 dark:text-slate-500 mt-px">{{ __('messages.fab_purchase_sub') }}</span>
                </span>
            </a>

            {{-- Pay a bill: the ones that want paying now, each opening the pay modal --}}
            <div class="{{ $sheetCard }} overflow-hidden">
                <button type="button" class="{{ $sheetRow }}" @click="panel = panel === 'pay' ? null : 'pay'"
                        :aria-expanded="panel === 'pay' ? 'true' : 'false'">
                    <span class="w-10 h-10 rounded-[13px] shrink-0 flex items-center justify-center bg-orange-500/[0.16] text-orange-500">
                        <span class="material-icons-round text-lg">check_circle</span>
                    </span>
                    <span class="flex-1 min-w-0">
                        <span class="block text-[0.9rem] font-bold text-gray-900 dark:text-white">{{ __('messages.fab_pay') }}</span>
                        <span class="block text-[0.72rem] text-gray-400 dark:text-slate-500 mt-px">
                            {{ trans_choice('messages.fab_pay_count', $qa['bills']->count(), ['count' => $qa['bills']->count()]) }}
                        </span>
                    </span>
                    <span class="material-icons-round text-gray-400 transition" :class="panel === 'pay' && 'rotate-180'">expand_more</span>
                </button>
                <div x-show="panel === 'pay'" x-cloak x-collapse>
                    @foreach($qa['bills'] as $bill)
                        <button type="button"
                                @click="fabOpen = false; panel = null; $dispatch('open-pay-modal', {{ \Illuminate\Support\Js::from($bill->payModalPayload()) }})"
                                class="flex items-center justify-between gap-3 w-full px-4 py-3 border-t border-gray-100 dark:border-slate-700 text-left">
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $bill->name }}</span>
                                <span class="block text-[0.7rem] {{ $bill->status() === 'overdue' ? 'text-red-500' : 'text-gray-400 dark:text-slate-500' }}">
                                    {{ $bill->next_due_date?->translatedFormat('j M') }}
                                </span>
                            </span>
                            <span class="text-sm font-bold text-gray-900 dark:text-white tabular-nums shrink-0">
                                {{ $bill->amountIsUnknown() ? __('messages.varies') : $bill->currency_code . ' ' . number_format($bill->amountDueNow(), 2) }}
                            </span>
                        </button>
                    @endforeach
                    <a href="{{ route('bills.index') }}" class="block px-4 py-3 border-t border-gray-100 dark:border-slate-700 text-xs font-semibold text-amber-700 dark:text-amber-400">
                        {{ __('messages.fab_all_bills') }}
                    </a>
                </div>
            </div>

            {{-- Income that came in: the sources due now, recorded in one tap --}}
            @if($qa['incomes']->isNotEmpty())
                <div class="{{ $sheetCard }} overflow-hidden">
                    <button type="button" class="{{ $sheetRow }}" @click="panel = panel === 'income' ? null : 'income'"
                            :aria-expanded="panel === 'income' ? 'true' : 'false'">
                        <span class="w-10 h-10 rounded-[13px] shrink-0 flex items-center justify-center bg-emerald-500/[0.16] text-emerald-500 dark:text-emerald-400">
                            <span class="material-icons-round text-lg">savings</span>
                        </span>
                        <span class="flex-1 min-w-0">
                            <span class="block text-[0.9rem] font-bold text-gray-900 dark:text-white">{{ __('messages.fab_income') }}</span>
                            <span class="block text-[0.72rem] text-gray-400 dark:text-slate-500 mt-px">
                                {{ trans_choice('messages.fab_income_count', $qa['incomes']->count(), ['count' => $qa['incomes']->count()]) }}
                            </span>
                        </span>
                        <span class="material-icons-round text-gray-400 transition" :class="panel === 'income' && 'rotate-180'">expand_more</span>
                    </button>
                    <div x-show="panel === 'income'" x-cloak x-collapse>
                        @foreach($qa['incomes'] as $income)
                            <form method="POST" action="{{ route('income.receive', $income) }}"
                                  class="flex items-center justify-between gap-3 px-4 py-3 border-t border-gray-100 dark:border-slate-700">
                                @csrf
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $income->name }}</span>
                                    <span class="block text-[0.7rem] text-gray-400 dark:text-slate-500">
                                        {{ $income->currency_code }} {{ number_format((float) $income->amount, 2) }} · {{ $income->next_date?->translatedFormat('j M') }}
                                    </span>
                                </span>
                                <button type="submit" class="shrink-0 h-9 px-3 rounded-xl bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 text-xs font-bold">
                                    {{ __('messages.fab_income_received') }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Fill-up: straight to the fuel form, or a pick of vehicles --}}
            @if($qa['vehicles']->count() === 1)
                <a href="{{ route('vehicles.show', ['vehicle' => $qa['vehicles']->first(), 'add' => 'fuel']) }}" class="{{ $sheetCard }} {{ $sheetRow }}">
                    <span class="w-10 h-10 rounded-[13px] shrink-0 flex items-center justify-center bg-sky-500/[0.16] text-sky-500 dark:text-sky-400">
                        <span class="material-icons-round text-lg">local_gas_station</span>
                    </span>
                    <span class="flex-1 min-w-0">
                        <span class="block text-[0.9rem] font-bold text-gray-900 dark:text-white">{{ __('messages.vehicle_add_fuel') }}</span>
                        <span class="block text-[0.72rem] text-gray-400 dark:text-slate-500 mt-px truncate">{{ $qa['vehicles']->first()->name }}</span>
                    </span>
                </a>
            @elseif($qa['vehicles']->count() > 1)
                <div class="{{ $sheetCard }} overflow-hidden">
                    <button type="button" class="{{ $sheetRow }}" @click="panel = panel === 'fuel' ? null : 'fuel'"
                            :aria-expanded="panel === 'fuel' ? 'true' : 'false'">
                        <span class="w-10 h-10 rounded-[13px] shrink-0 flex items-center justify-center bg-sky-500/[0.16] text-sky-500 dark:text-sky-400">
                            <span class="material-icons-round text-lg">local_gas_station</span>
                        </span>
                        <span class="flex-1 min-w-0">
                            <span class="block text-[0.9rem] font-bold text-gray-900 dark:text-white">{{ __('messages.vehicle_add_fuel') }}</span>
                            <span class="block text-[0.72rem] text-gray-400 dark:text-slate-500 mt-px">{{ __('messages.fab_fuel_pick') }}</span>
                        </span>
                        <span class="material-icons-round text-gray-400 transition" :class="panel === 'fuel' && 'rotate-180'">expand_more</span>
                    </button>
                    <div x-show="panel === 'fuel'" x-cloak x-collapse>
                        @foreach($qa['vehicles'] as $vehicle)
                            <a href="{{ route('vehicles.show', ['vehicle' => $vehicle, 'add' => 'fuel']) }}"
                               class="flex items-center gap-3 px-4 py-3 border-t border-gray-100 dark:border-slate-700 text-sm font-semibold text-gray-900 dark:text-white">
                                <span class="material-icons-round text-base text-gray-400">{{ $vehicle->icon() }}</span>{{ $vehicle->name }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- The FAB itself sits above the sheet backdrop so it can close it --}}
        <button type="button" @click="fabOpen = !fabOpen; panel = null"
                :aria-expanded="fabOpen ? 'true' : 'false'"
                aria-label="{{ __('messages.add') }}"
                class="fixed left-1/2 -translate-x-1/2 bottom-[calc(30px+env(safe-area-inset-bottom))] z-[43] flex items-center justify-center w-14 h-14 rounded-full border-4 border-white dark:border-slate-800 bg-amber-500 transition-transform"
                :class="fabOpen ? 'rotate-45' : ''"
                style="box-shadow: 0 4px 24px rgba(245,158,11,0.45);">
            <span class="material-icons-round text-slate-900 text-2xl">add</span>
        </button>
    </div>

    {{-- Mobile bottom navigation: four labelled tabs either side of the "+".
         Labels because icons alone did not say where they led. The rest of
         the app is in the menu (☰) at the top. --}}
    <nav class="lg:hidden fixed inset-x-0 bottom-0 z-40 bg-white dark:bg-slate-800 border-t border-gray-100 dark:border-slate-700"
         style="box-shadow: 0 -6px 24px rgba(0,0,0,0.14); padding-bottom: env(safe-area-inset-bottom);"
         aria-label="Mobile primary navigation">
        @php
            $bottomNavLinks = [
                ['route' => 'dashboard',      'icon' => 'dashboard',    'match' => 'dashboard', 'label' => __('messages.nav_home')],
                ['route' => 'bills.index',    'icon' => 'receipt_long', 'match' => 'bills.*', 'count' => $overdueBillCount ?? 0, 'label' => __('messages.bills')],
                ['route' => 'expenses.index', 'icon' => 'shopping_bag', 'match' => 'expenses.*', 'label' => __('messages.expenses_menu')],
                ['route' => 'income.index',   'icon' => 'trending_up',  'match' => ['income.*', 'accounts.*'], 'label' => __('messages.nav_income_short')],
            ];
        @endphp
        <div class="grid grid-cols-[1fr_1fr_4.5rem_1fr_1fr] h-16 items-stretch">
            @foreach($bottomNavLinks as $i => $link)
                @if($i === 2)
                    {{-- Room for the "+" --}}
                    <div aria-hidden="true"></div>
                @endif
                @php $active = request()->routeIs(...(array) $link['match']); @endphp
                <a href="{{ route($link['route']) }}"
                   @if($active) aria-current="page" @endif
                   class="relative flex flex-col items-center justify-center gap-0.5 min-w-0
                          {{ $active ? 'text-amber-700 dark:text-amber-400' : 'text-gray-400 dark:text-slate-500' }}">
                    <span class="relative">
                        <span class="material-icons-round" style="font-size:23px;" aria-hidden="true">{{ $link['icon'] }}</span>
                        @if(($link['count'] ?? 0) > 0)
                            <span class="absolute -top-1 -right-2.5 min-w-4 h-4 px-1 inline-flex items-center justify-center
                                         rounded-full bg-red-500 text-white text-[0.6rem] font-bold leading-none"
                                  aria-label="{{ trans_choice('messages.overdue_count', $link['count'], ['count' => $link['count']]) }}">
                                {{ $link['count'] > 9 ? '9+' : $link['count'] }}
                            </span>
                        @endif
                    </span>
                    <span class="text-[0.66rem] leading-tight truncate max-w-full px-1 {{ $active ? 'font-bold' : 'font-medium' }}">{{ $link['label'] }}</span>
                </a>
            @endforeach
        </div>
    </nav>
</div>
@auth
    @include('partials.pay-modal')
@endauth
@include('partials.toast')
@stack('scripts')
</body>
</html>
