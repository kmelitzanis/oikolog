<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('messages.dashboard')) — Oikolog</title>
    @include('partials.pwa')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    {{-- Prevent dark-mode flash before JS loads --}}
    <script>
        (function () {
            var t = localStorage.getItem('theme');
            if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    @php
        $manifestPath = public_path('build/manifest.json');
        $manifest = file_exists($manifestPath) ? (json_decode(file_get_contents($manifestPath), true) ?: []) : [];
        $entry = $manifest['resources/js/app.js'] ?? null;
    @endphp
    @if($entry)
        @if(!empty($entry['css'][0]))
            <link rel="stylesheet" href="{{ asset('build/'.$entry['css'][0]) }}">
        @endif
        <script defer src="{{ asset('build/'.$entry['file']) }}"></script>
    @else
        @vite(['resources/js/app.js'])
    @endif
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
            localStorage.setItem('theme', this.isDark ? 'dark' : 'light');
        }
     }"
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
                    ['route' => 'bills.index',       'icon' => 'receipt_long', 'label' => __('messages.bills'),        'match' => 'bills.*'],
                    ['route' => 'income.index',      'icon' => 'trending_up',  'label' => __('messages.income'),       'match' => ['income.*', 'accounts.*']],
                    ['route' => 'family.index',      'icon' => 'group',        'label' => __('messages.family'),       'match' => 'family.*'],
                    ['route' => 'shopping-list.index', 'icon' => 'shopping_cart', 'label' => __('messages.shopping_lists'), 'match' => 'shopping-list.*'],
                    ['route' => 'recipes.index',     'icon' => 'restaurant_menu','label' => __('messages.recipes'),      'match' => 'recipes.*'],
                    ['route' => 'meal-plans.index',  'icon' => 'calendar_view_week', 'label' => __('messages.meal_planner'), 'match' => 'meal-plans.*'],
                ];
            @endphp
            @foreach($navLinks as $link)
                <a href="{{ route($link['route']) }}" @click="sidebarOpen=false"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors
                          {{ request()->routeIs(...(array) $link['match'])
                              ? 'bg-amber-50 dark:bg-amber-500/15 text-amber-700 dark:text-amber-400'
                              : 'text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-white' }}">
                    <span class="material-icons-round text-xl">{{ $link['icon'] }}</span>
                    <span>{{ $link['label'] }}</span>
                </a>
            @endforeach
            @if(auth()->check() && auth()->user()->isAdmin())
                <div class="pt-3 mt-2 border-t border-gray-100 dark:border-slate-700">
                    <p class="px-3 pb-1.5 text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider">{{ __('messages.admin') }}</p>
                    @foreach([
                        ['route'=>'admin.categories.index','icon'=>'category',        'label'=>__('messages.categories'),'match'=>'admin.categories.*'],
                        ['route'=>'admin.providers.index', 'icon'=>'business',        'label'=>__('messages.providers'),  'match'=>'admin.providers.*'],
                        ['route'=>'admin.products.index',  'icon'=>'inventory_2',    'label'=>__('messages.products'),   'match'=>'admin.products.*'],
                        ['route'=>'admin.users.index',     'icon'=>'manage_accounts', 'label'=>__('messages.users'),      'match'=>'admin.users.*'],
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
            <div
                class="relative flex items-center gap-3 p-2 rounded-xl hover:bg-gray-50 dark:hover:bg-slate-700 transition cursor-pointer"
                @click="userMenuOpen=!userMenuOpen">
                <div
                    class="w-9 h-9 rounded-xl bg-amber-500 flex items-center justify-center text-slate-900 font-bold text-sm shrink-0 overflow-hidden">
                    @if(auth()->user()?->avatar_url)
                        <img src="{{ auth()->user()->avatar_url }}" class="w-full h-full object-cover" alt="">
                    @else
                        {{ strtoupper(substr(auth()->user()?->name ?? '?', 0, 1)) }}
                    @endif
                </div>
                <div class="flex-1 min-w-0">
                    <div
                        class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ auth()->user()?->name }}</div>
                    <div class="text-xs text-gray-400 dark:text-slate-500">{{ auth()->user()?->currency_code }}</div>
                </div>
                <span class="material-icons-round text-gray-400 dark:text-slate-500 text-lg">expand_more</span>
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
                    <span x-text="isDark ? '{{ __('messages.light') }}' : '{{ __('messages.dark') }}'"></span>
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
        class="lg:hidden fixed inset-x-0 top-0 z-30 bg-white dark:bg-slate-800 border-b border-gray-100 dark:border-slate-700">
        <div class="flex items-center justify-between px-4 h-14">
            <div class="flex items-center gap-3">
                <button @click="sidebarOpen=true"
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
                            {{ strtoupper(substr(auth()->user()?->name ?? '?', 0, 1)) }}
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
                            <span x-text="isDark ? '{{ __('messages.light') }}' : '{{ __('messages.dark') }}'"></span>
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
        <main class="min-h-screen px-4 sm:px-6 lg:px-8 pt-20 lg:pt-8 pb-28 lg:pb-12 max-w-7xl mx-auto">
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
    {{-- ── Mobile FAB action sheet (mockup 2a) ───────────────────────────
         The FAB used to link straight to "new bill"; the mockup opens a set of
         choices above the bar instead. The mockup's fourth action is receipt
         scanning, which the app has no feature for, so that slot carries a
         real one — a new shopping list. --}}
    <div x-data="{ fabOpen: false }" @keydown.escape.window="fabOpen = false" class="lg:hidden">
        <div x-show="fabOpen" x-cloak x-transition.opacity.duration.180ms
             class="fixed inset-0 z-[41] bg-slate-950/[0.72] backdrop-blur-md"
             @click="fabOpen = false"></div>

        <div x-show="fabOpen" x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-2.5"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="fixed left-4 right-4 bottom-[118px] z-[42] flex flex-col gap-[9px]">
            @php
                $fabActions = [
                    ['route' => route('bills.create'),  'icon' => 'credit_card',    'tint' => 'bg-amber-500/[0.18] text-amber-300',   'title' => __('messages.add_bill'),        'sub' => __('messages.fab_bill_sub')],
                    ['route' => route('income.create'), 'icon' => 'savings',        'tint' => 'bg-emerald-500/[0.16] text-emerald-400','title' => __('messages.add_income'),      'sub' => __('messages.fab_income_sub')],
                    ['route' => route('bills.index', ['status' => 'overdue']), 'icon' => 'check_circle', 'tint' => 'bg-orange-500/[0.16] text-orange-500', 'title' => __('messages.record_payment'), 'sub' => __('messages.fab_pay_sub')],
                    ['route' => route('shopping-list.index'), 'icon' => 'shopping_cart', 'tint' => 'bg-amber-600/[0.16] text-amber-400', 'title' => __('messages.new_list'),  'sub' => __('messages.fab_list_sub')],
                ];
            @endphp
            @foreach($fabActions as $action)
                <a href="{{ $action['route'] }}"
                   class="flex items-center gap-[13px] p-4 rounded-[19px] border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-left transition active:scale-[0.98]">
                    <span class="w-10 h-10 rounded-[13px] shrink-0 flex items-center justify-center {{ $action['tint'] }}">
                        <span class="material-icons-round text-lg">{{ $action['icon'] }}</span>
                    </span>
                    <span class="flex-1 min-w-0">
                        <span class="block text-[0.9rem] font-bold text-gray-900 dark:text-white">{{ $action['title'] }}</span>
                        <span class="block text-[0.72rem] text-gray-400 dark:text-slate-500 mt-px">{{ $action['sub'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>

        {{-- The FAB itself sits above the sheet backdrop so it can close it --}}
        <button type="button" @click="fabOpen = !fabOpen"
                :aria-expanded="fabOpen ? 'true' : 'false'"
                aria-label="{{ __('messages.add') }}"
                class="fixed left-1/2 -translate-x-1/2 bottom-[26px] z-[43] flex items-center justify-center w-14 h-14 rounded-full border-4 border-white dark:border-slate-800 bg-amber-500 transition-transform"
                :class="fabOpen ? 'rotate-45' : ''"
                style="box-shadow: 0 4px 24px rgba(245,158,11,0.45);">
            <span class="material-icons-round text-slate-900 text-2xl">add</span>
        </button>
    </div>

    {{-- Mobile Bottom Navigation Bar --}}
    <nav class="lg:hidden fixed inset-x-0 bottom-0 z-40" aria-label="Mobile primary navigation">

        @php
            $bottomNavLinks = [
                ['route' => 'dashboard',    'icon' => 'dashboard',    'match' => 'dashboard'],
                ['route' => 'bills.index',  'icon' => 'receipt_long', 'match' => 'bills.*'],
                ['route' => 'recipes.index','icon' => 'restaurant_menu','match' => 'recipes.*'],
                ['route' => 'income.index', 'icon' => 'trending_up',  'match' => ['income.*', 'accounts.*']],
            ];
        @endphp

        {{-- Flex column: items-center naturally centers the FAB; -mb-7 pulls the bar up underneath it --}}
        <div class="flex flex-col items-center">

            {{-- The FAB lives above, outside this bar, so it can sit over the
                 action sheet's backdrop and double as its close control. --}}

            {{-- Floating bottom bar --}}
            <div class="w-full bg-white dark:bg-slate-800 h-16 flex items-center justify-between px-6"
                 style="box-shadow: 0 8px 32px rgba(0,0,0,0.18), 0 2px 8px rgba(0,0,0,0.10);">

                @foreach(array_slice($bottomNavLinks, 0, 2) as $link)
                    <a href="{{ route($link['route']) }}"
                       class="flex flex-col items-center justify-center gap-1 w-12
                                  {{ request()->routeIs(...(array) $link['match']) ? 'text-amber-700 dark:text-amber-400' : 'text-gray-400 dark:text-slate-500' }}">
                        <span class="material-icons-round" style="font-size:22px;">{{ $link['icon'] }}</span>
                        <span
                            class="w-1.5 h-1.5 rounded-full {{ request()->routeIs(...(array) $link['match']) ? 'bg-amber-500' : 'bg-transparent' }}"></span>
                    </a>
                @endforeach

                {{-- Spacer keeps icons away from center where FAB overlaps --}}
                <div class="w-14 shrink-0"></div>

                @foreach(array_slice($bottomNavLinks, 2, 2) as $link)
                    <a href="{{ route($link['route']) }}"
                       class="flex flex-col items-center justify-center gap-1 w-12
                                  {{ request()->routeIs(...(array) $link['match']) ? 'text-amber-700 dark:text-amber-400' : 'text-gray-400 dark:text-slate-500' }}">
                        <span class="material-icons-round" style="font-size:22px;">{{ $link['icon'] }}</span>
                        <span
                            class="w-1.5 h-1.5 rounded-full {{ request()->routeIs(...(array) $link['match']) ? 'bg-amber-500' : 'bg-transparent' }}"></span>
                    </a>
                @endforeach
            </div>

        </div>
        {{-- Safe-area spacer for notched iPhones --}}
        <div style="height: env(safe-area-inset-bottom);"></div>

    </nav>
</div>
@auth
    @include('partials.pay-modal')
@endauth
@include('partials.toast')
@stack('scripts')
</body>
</html>
