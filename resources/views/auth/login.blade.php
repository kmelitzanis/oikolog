@extends('layouts.guest')
@section('title', __('messages.sign_in'))
@section('content')
    @php
        $input = 'w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-base sm:text-sm outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 dark:focus:ring-amber-500/30 transition';
        $label = 'block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5';
    @endphp

    <div class="text-center mb-8">
        <x-logo size="lg" class="mb-1" />
        <p class="text-sm text-gray-400 dark:text-slate-400 mt-1">{{ __('messages.sign_in_title') }}</p>
    </div>

    @if($errors->any())
        <div role="alert"
             class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 rounded-xl px-4 py-3 text-sm mb-6">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('login.post') }}" class="space-y-5"
          x-data="{ submitting: false }" @submit="submitting = true">
        @csrf
        <div>
            <label for="email" class="{{ $label }}">{{ __('messages.email') }}</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="you@example.com"
                   required autofocus autocomplete="username" class="{{ $input }}">
        </div>
        <div>
            <label for="password" class="{{ $label }}">{{ __('messages.password') }}</label>
            <input type="password" id="password" name="password" placeholder="••••••••" required
                   autocomplete="current-password" class="{{ $input }}">
        </div>
        <div class="flex items-center">
            <input type="checkbox" name="remember" id="remember" value="1" @checked(old('remember'))
                   class="w-4 h-4 rounded border-gray-300 dark:border-slate-600 text-amber-600 focus:ring-amber-500 cursor-pointer">
            <label for="remember" class="ml-2 text-sm text-gray-500 dark:text-slate-400 cursor-pointer select-none">{{ __('messages.remember_me') }}</label>
        </div>
        <button type="submit" :disabled="submitting"
                class="w-full bg-amber-500 hover:bg-amber-600 disabled:opacity-60 disabled:cursor-wait text-slate-900 font-semibold rounded-xl py-3 text-sm transition">
            {{ __('messages.sign_in') }}
        </button>
    </form>
@endsection
