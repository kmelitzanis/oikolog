{{-- PWA: manifest, icons, theme color and service-worker registration --}}
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
{{-- SVG first for browsers that support it, .ico as the fallback --}}
<link rel="icon" type="image/svg+xml" href="{{ asset('icons/icon.svg') }}">
<link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}" sizes="16x16 32x32 48x48">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/icon-192.png') }}">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
{{-- Browser chrome tint. Split by scheme so dark mode gets the app's own navy
     rather than a bright blue band above a dark page. --}}
<meta name="theme-color" content="#2563eb" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0f172a" media="(prefers-color-scheme: dark)">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Oikolog">
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('{{ asset('sw.js') }}').catch(function () {
                // Registration failure (e.g. http without localhost) is non-fatal.
            });
        });
    }
</script>
