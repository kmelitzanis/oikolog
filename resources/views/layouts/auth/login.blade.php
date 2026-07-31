<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Oikolog</title>
    @include('partials.pwa')
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    @php $m = json_decode(file_get_contents(public_path('build/manifest.json')),true); $e = $m['resources/js/app.js'] ?? null; @endphp
    @if($e)
        @if(!empty($e['css'][0]))
            <link rel="stylesheet" href="{{ asset('build/'.$e['css'][0]) }}">
        @endif
        <script defer src="{{ asset('build/'.$e['file']) }}"></script>
    @endif
</head>
<body class="min-h-screen bg-linear-to-br from-amber-50 to-amber-50 flex items-center justify-center p-6">

<div class="w-full max-w-md bg-white rounded-3xl border border-gray-200 shadow-xl p-10">

    {{-- Logo --}}
    <div class="text-center mb-8">
        <x-logo size="lg" class="mb-1" />
        <p class="text-sm text-gray-400 mt-1">Sign in to your account</p>
    </div>

    {{-- Errors --}}
    @if(isset($errors) && $errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm mb-6">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('login.post') }}" class="space-y-5">
        @csrf
        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1.5">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" required
                   autocomplete="email"
                   class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1.5">Password</label>
            <input type="password" name="password" placeholder="••••••••" required
                   autocomplete="current-password"
                   class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition">
        </div>
        <div class="flex items-center">
            <input type="checkbox" name="remember" id="remember" value="1"
                   class="w-4 h-4 rounded border-gray-300 text-amber-700 focus:ring-amber-500 cursor-pointer">
            <label for="remember" class="ml-2 text-sm text-gray-500 cursor-pointer select-none">Remember me</label>
        </div>
        <button type="submit"
                class="w-full bg-amber-500 hover:bg-amber-600 text-slate-900 font-semibold rounded-xl py-3 text-sm transition">
            Sign In
        </button>
    </form>

</div>
</body>
</html>
