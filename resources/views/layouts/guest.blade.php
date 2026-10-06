<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>@yield('title') — Oikolog</title>
    @include('partials.pwa')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    {{-- Same theme bootstrap as the app shell, so signing in does not flash
         a white page at someone who uses the dark theme. --}}
    <script>
        (function () {
            try {
                var t = localStorage.getItem('theme');
                if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>
    @include('partials.assets')
</head>
<body class="min-h-screen bg-linear-to-br from-amber-50 to-orange-50 dark:from-slate-900 dark:to-slate-950 font-sans antialiased flex items-center justify-center p-4 sm:p-6">

<main class="w-full max-w-md bg-white dark:bg-slate-800 rounded-3xl border border-gray-200 dark:border-slate-700 shadow-xl p-7 sm:p-10">
    @yield('content')
</main>

</body>
</html>
