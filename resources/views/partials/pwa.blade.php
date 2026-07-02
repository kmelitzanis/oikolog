{{-- PWA: manifest, icons, theme color and service-worker registration --}}
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/icon-192.png') }}">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
<meta name="theme-color" content="#4f46e5">
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
