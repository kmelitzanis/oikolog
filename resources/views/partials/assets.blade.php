{{-- Built CSS/JS. Read from the Vite manifest directly so a production page
     never depends on the dev server; with neither a build nor a running dev
     server the page still renders (unstyled) instead of failing with a 500. --}}
@php
    $manifestPath = public_path('build/manifest.json');
    $manifest = is_file($manifestPath) ? (json_decode(file_get_contents($manifestPath), true) ?: []) : [];
    $entry = $manifest['resources/js/app.js'] ?? null;
@endphp
@if($entry)
    @foreach($entry['css'] ?? [] as $css)
        <link rel="stylesheet" href="{{ asset('build/'.$css) }}">
    @endforeach
    <script defer src="{{ asset('build/'.$entry['file']) }}"></script>
@elseif(is_file(public_path('hot')))
    @vite(['resources/js/app.js'])
@endif
