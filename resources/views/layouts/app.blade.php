<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — Oikolog</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
<body class="bg-gray-50 font-sans antialiased">

<div x-data="{ sidebarOpen: false, userMenuOpen: false }" class="min-h-screen flex">

    {{-- Mobile backdrop --}}
    <div x-show="sidebarOpen"
         x-transition:enter="transition-opacity duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="sidebarOpen=false"
         class="fixed inset-0 bg-black/40 z-30 lg:hidden"
         x-cloak></div>

    {{-- ── Sidebar ─────────────────────────────────────────────── --}}
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-40 w-64 bg-white border-r border-gray-100 flex flex-col
                  transform transition-transform duration-200 ease-in-out lg:translate-x-0">

        {{-- Brand --}}
        <div class="flex items-center gap-3 px-5 py-5 border-b border-gray-100">
            <div
                class="w-9 h-9 bg-linear-to-br from-indigo-600 to-indigo-500 rounded-xl flex items-center justify-center shrink-0">
                <span class="material-icons-round text-white text-xl">account_balance_wallet</span>
            </div>
            <span class="font-extrabold text-lg text-gray-900">Oikolog</span>
        </div>

        {{-- Nav --}}
        <nav class="flex-1 px-3 py-5 space-y-1 overflow-y-auto">
            @php
                $navLinks = [
                    ['route' => 'dashboard',    'icon' => 'dashboard',     'label' => __('messages.dashboard'), 'match' => 'dashboard'],
                    ['route' => 'bills.index',  'icon' => 'receipt_long',  'label' => __('messages.bills'),     'match' => 'bills.*'],
                    ['route' => 'family.index', 'icon' => 'group',         'label' => __('messages.family'),    'match' => 'family.*'],
                ];
            @endphp
            @foreach($navLinks as $link)
                <a href="{{ route($link['route']) }}"
                   @click="sidebarOpen=false"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors
                          {{ request()->routeIs($link['match']) ? 'bg-indigo-50 text-indigo-600' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <span class="material-icons-round text-xl">{{ $link['icon'] }}</span>
                    <span>{{ $link['label'] }}</span>
                </a>
            @endforeach
        </nav>

        {{-- User footer --}}
        <div class="px-3 py-4 border-t border-gray-100">
            <div class="relative flex items-center gap-3 p-2 rounded-xl hover:bg-gray-50 transition cursor-pointer"
                 @click="userMenuOpen=!userMenuOpen">
                <div
                    class="w-9 h-9 rounded-xl bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold text-sm shrink-0">
                    @if(auth()->user()?->avatar_url)
                        <img src="{{ auth()->user()->avatar_url }}" class="w-full h-full object-cover rounded-xl"
                             alt="">
                    @else
                        {{ strtoupper(substr(auth()->user()?->name ?? '?', 0, 1)) }}
                    @endif
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-semibold text-gray-900 truncate">{{ auth()->user()?->name }}</div>
                    <div class="text-xs text-gray-400">{{ auth()->user()?->currency_code }}</div>
                </div>
                <span class="material-icons-round text-gray-400 text-lg">expand_more</span>
            </div>

            {{-- User dropdown --}}
            <div x-show="userMenuOpen"
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-75"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 @click.outside="userMenuOpen=false"
                 class="absolute bottom-20 left-3 right-3 bg-white border border-gray-100 rounded-2xl shadow-lg overflow-hidden z-50"
                 x-cloak>
                <a href="{{ route('settings') }}"
                   class="flex items-center gap-3 px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 transition">
                    <span class="material-icons-round text-gray-400 text-lg">settings</span>
                    {{ __('messages.settings') }}
                </a>
                <div class="flex gap-0 border-t border-gray-100">
                    @php $langLabels = ['en'=>'EN','el'=>'ΕΛ']; @endphp
                    @foreach($availableLocales ?? ['en'] as $loc)
                        <a href="{{ route('locale.set', $loc) }}"
                           data-locale="{{ $loc }}"
                           class="locale-switch flex-1 text-center py-2.5 text-xs font-bold transition {{ app()->getLocale()===$loc ? 'text-indigo-600 bg-indigo-50' : 'text-gray-400 hover:text-gray-700' }}"
                           aria-label="Switch language to {{ $loc }}">{{ $langLabels[$loc] ?? strtoupper($loc) }}</a>
                    @endforeach
                </div>
                <form method="POST" action="{{ route('logout') }}" class="border-t border-gray-100">
                    @csrf
                    <button type="submit"
                            class="w-full text-left px-4 py-3 text-sm text-red-600 hover:bg-red-50 transition flex items-center gap-3">
                        <span class="material-icons-round text-lg">logout</span>
                        {{ __('messages.logout') }}
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- ── Mobile topbar ────────────────────────────────────────── --}}
    <div class="lg:hidden fixed inset-x-0 top-0 z-30 bg-white border-b border-gray-100">
        <div class="flex items-center justify-between px-4 h-14">
            <div class="flex items-center gap-3">
                <button @click="sidebarOpen=true"
                        class="p-2 -ml-2 rounded-xl text-gray-600 hover:bg-gray-100 transition">
                    <span class="material-icons-round">menu</span>
                </button>
                <span class="font-extrabold text-gray-900">Oikolog</span>
            </div>
            <a href="{{ route('bills.create') }}" class="p-2 rounded-xl text-indigo-600 hover:bg-indigo-50 transition">
                <span class="material-icons-round">add_circle</span>
            </a>
        </div>
    </div>

    {{-- ── Page content ─────────────────────────────────────────── --}}
    <div class="flex-1 lg:pl-64 min-w-0">
        <main class="min-h-screen px-4 sm:px-6 lg:px-8 pt-20 lg:pt-8 pb-12 max-w-7xl mx-auto">

            @if(session('success'))
                <div
                    class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-700 rounded-2xl px-4 py-3 text-sm mb-6">
                    <span class="material-icons-round text-green-500 text-lg">check_circle</span>
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div
                    class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 rounded-2xl px-4 py-3 text-sm mb-6">
                    <span class="material-icons-round text-red-500 text-lg">error</span>
                    {{ session('error') }}
                </div>
            @endif

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 rounded-2xl px-4 py-3 text-sm mb-6">
                    <div class="flex items-center gap-2 font-semibold mb-1">
                        <span class="material-icons-round text-lg">error</span>
                        {{ __('messages.whoops') }}
                    </div>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

</div>

@stack('scripts')
<script>
    // Ensure language switching performs a full navigation and reloads the page.
    (function () {
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.locale-switch').forEach(function (el) {
                el.addEventListener('click', function (e) {
                    e.preventDefault();
                    var href = el.getAttribute('href');
                    // set location directly to perform navigation (avoids Alpine interference)
                    window.location.href = href;
                }, {passive: false});
            });
        });
    })();
</script>
</body>
</html>
