{{-- Built CSS/JS.

     A running Vite dev server (`pnpm run dev` writes public/hot) wins, as in
     stock Laravel. Preferring the manifest instead served whatever was last
     built: Tailwind only emits the classes it found at build time, so any page
     using a newer class rendered half-styled until someone rebuilt.

     Otherwise the manifest is read directly so production never needs the dev
     server; with neither, the page still renders (unstyled) instead of a 500. --}}
@php
    $manifestPath = public_path('build/manifest.json');
    $manifest = is_file($manifestPath) ? (json_decode(file_get_contents($manifestPath), true) ?: []) : [];
    $entry = $manifest['resources/js/app.js'] ?? null;
@endphp
@if(is_file(public_path('hot')))
    @vite(['resources/js/app.js'])
@elseif($entry)
    @foreach($entry['css'] ?? [] as $css)
        <link rel="stylesheet" href="{{ asset('build/'.$css) }}">
    @endforeach
    <script defer src="{{ asset('build/'.$entry['file']) }}"></script>
@endif
