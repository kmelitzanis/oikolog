@extends('layouts.guest')
@section('title', __('messages.two_factor_auth'))
@section('content')
    <div class="text-center mb-8">
        <div class="w-14 h-14 bg-linear-to-br from-amber-500 to-amber-400 rounded-2xl inline-flex items-center justify-center mb-4 shadow-lg">
            <span class="material-icons-round text-slate-900 text-3xl" aria-hidden="true">lock</span>
        </div>
        <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">{{ __('messages.two_factor_auth') }}</h1>
        <p class="text-sm text-gray-400 dark:text-slate-400 mt-1">{{ __('messages.two_factor_enter_code') }}</p>
    </div>

    @if($errors->any())
        <div role="alert"
             class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 rounded-xl px-4 py-3 text-sm mb-6">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('2fa.verify') }}" class="space-y-5"
          x-data="{ submitting: false }" @submit="submitting = true">
        @csrf
        <div>
            <label for="code" class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">{{ __('messages.authentication_code') }}</label>
            <input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code"
                   pattern="[0-9 ]*" maxlength="7" placeholder="000 000" required autofocus
                   class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-base text-center tracking-widest outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 dark:focus:ring-amber-500/30 transition">
        </div>
        <button type="submit" :disabled="submitting"
                class="w-full bg-amber-500 hover:bg-amber-600 disabled:opacity-60 disabled:cursor-wait text-slate-900 font-semibold rounded-xl py-3 text-sm transition">
            {{ __('messages.verify') }}
        </button>
        <a href="{{ route('login') }}"
           class="flex items-center justify-center gap-1 text-sm text-gray-400 dark:text-slate-400 hover:text-gray-600 dark:hover:text-slate-200 transition">
            <span class="material-icons-round text-base" aria-hidden="true">arrow_back</span>
            {{ __('messages.back_to_login') }}
        </a>
    </form>
@endsection
