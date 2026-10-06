@extends('layouts.app')
@section('title', __('messages.two_factor_auth'))
@section('content')
    @php
        $codeInput = 'w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-base text-center tracking-widest outline-none transition';
    @endphp
    <div class="max-w-xl">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">{{ __('messages.two_factor_auth') }}</h1>
        </div>

        <x-card flush class="p-6 space-y-6">

            @if($enabled)
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-green-50 dark:bg-green-900/30 flex items-center justify-center shrink-0">
                        <span class="material-icons-round text-green-600 dark:text-green-400" aria-hidden="true">verified_user</span>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-800 dark:text-white">{{ __('messages.two_factor_is_enabled') }}</p>
                        <p class="text-xs text-gray-400 dark:text-slate-400">{{ __('messages.two_factor_enabled_hint') }}</p>
                    </div>
                </div>

                <hr class="border-gray-100 dark:border-slate-700">

                <p class="text-sm text-gray-500 dark:text-slate-400">{{ __('messages.two_factor_disable_hint') }}</p>

                <form method="POST" action="{{ route('2fa.disable') }}">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label for="disable-code" class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">{{ __('messages.authentication_code') }}</label>
                            <input type="text" id="disable-code" name="code" inputmode="numeric" autocomplete="one-time-code"
                                   pattern="[0-9 ]*" maxlength="7" placeholder="000 000" required
                                   class="{{ $codeInput }} focus:border-red-400 focus:ring-2 focus:ring-red-100 dark:focus:ring-red-900">
                        </div>
                        <button type="submit"
                                class="w-full flex items-center justify-center gap-2 bg-red-500 hover:bg-red-600 text-white font-semibold rounded-xl py-3 text-sm transition">
                            <span class="material-icons-round text-lg" aria-hidden="true">lock_open</span> {{ __('messages.two_factor_disable') }}
                        </button>
                    </div>
                </form>

            @else
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center shrink-0">
                        <span class="material-icons-round text-amber-500" aria-hidden="true">shield</span>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-800 dark:text-white">{{ __('messages.two_factor_is_disabled') }}</p>
                        <p class="text-xs text-gray-400 dark:text-slate-400">{{ __('messages.two_factor_setup_hint') }}</p>
                    </div>
                </div>

                <hr class="border-gray-100 dark:border-slate-700">

                <div class="flex justify-center">
                    {{-- Rendered by bacon-qr-code from our own otpauth URL. --}}
                    <div class="p-3 bg-white rounded-xl border border-gray-200 shadow-sm inline-block">
                        {!! $qrCodeSvg !!}
                    </div>
                </div>

                <div class="bg-gray-50 dark:bg-slate-700/50 rounded-xl p-3 text-center">
                    <p class="text-xs text-gray-400 dark:text-slate-400 mb-1">{{ __('messages.manual_entry_key') }}</p>
                    <p class="font-mono text-sm font-semibold text-gray-700 dark:text-slate-200 tracking-widest break-all select-all">{{ $secret }}</p>
                </div>

                <form method="POST" action="{{ route('2fa.enable') }}">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label for="enable-code" class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">{{ __('messages.two_factor_confirm_code') }}</label>
                            <input type="text" id="enable-code" name="code" inputmode="numeric" autocomplete="one-time-code"
                                   pattern="[0-9 ]*" maxlength="7" placeholder="000 000" required
                                   class="{{ $codeInput }} focus:border-amber-500 focus:ring-2 focus:ring-amber-100 dark:focus:ring-amber-500/30">
                        </div>
                        <button type="submit"
                                class="w-full flex items-center justify-center gap-2 bg-amber-500 hover:bg-amber-600 text-slate-900 font-semibold rounded-xl py-3 text-sm transition">
                            <span class="material-icons-round text-lg" aria-hidden="true">verified_user</span> {{ __('messages.two_factor_enable') }}
                        </button>
                    </div>
                </form>
            @endif

        </x-card>

        <div class="mt-4">
            <a href="{{ route('settings') }}"
               class="text-sm text-gray-400 hover:text-amber-700 dark:hover:text-amber-400 transition inline-flex items-center gap-1">
                <span class="material-icons-round text-base" aria-hidden="true">arrow_back</span> {{ __('messages.back_to_settings') }}
            </a>
        </div>
    </div>
@endsection
