@extends('layouts.app')
@section('title', 'Two-Factor Authentication')
@section('content')
    <div class="max-w-xl">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">Two-Factor Authentication</h1>
        </div>

        @if(session('success'))
            <div
                class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 text-green-700 dark:text-green-300 rounded-xl px-4 py-3 text-sm mb-6">
                {{ session('success') }}
            </div>
        @endif

        <x-card flush class="p-6 space-y-6">

            @if($enabled)
                {{-- 2FA is enabled --}}
                <div class="flex items-center gap-3">
                    <div
                        class="w-10 h-10 rounded-xl bg-green-50 dark:bg-green-900/30 flex items-center justify-center shrink-0">
                        <span class="material-icons-round text-green-600 dark:text-green-400">verified_user</span>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-800 dark:text-white">2FA is enabled</p>
                        <p class="text-xs text-gray-400 dark:text-slate-400">Your account is protected with an
                            authenticator app.</p>
                    </div>
                </div>

                <hr class="border-gray-100 dark:border-slate-700">

                <p class="text-sm text-gray-500 dark:text-slate-400">
                    To disable two-factor authentication, enter your current authenticator code below.
                </p>

                @if($errors->any())
                    <div
                        class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 text-red-700 dark:text-red-300 rounded-xl px-4 py-3 text-sm">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('2fa.disable') }}">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">Authentication
                                Code</label>
                            <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
                                   placeholder="000 000" required
                                   class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm text-center tracking-widest outline-none focus:border-red-400 focus:ring-2 focus:ring-red-100 dark:focus:ring-red-900 transition">
                        </div>
                        <button type="submit"
                                class="w-full flex items-center justify-center gap-2 bg-red-500 hover:bg-red-600 text-white font-semibold rounded-xl py-3 text-sm transition">
                            <span class="material-icons-round text-lg">lock_open</span> Disable 2FA
                        </button>
                    </div>
                </form>

            @else
                {{-- 2FA is disabled — show setup --}}
                <div class="flex items-center gap-3">
                    <div
                        class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center shrink-0">
                        <span class="material-icons-round text-amber-500">shield</span>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-800 dark:text-white">2FA is not enabled</p>
                        <p class="text-xs text-gray-400 dark:text-slate-400">Scan the QR code with your authenticator
                            app then confirm below.</p>
                    </div>
                </div>

                <hr class="border-gray-100 dark:border-slate-700">

                <div class="flex justify-center">
                    <div class="p-3 bg-white rounded-xl border border-gray-200 shadow-sm inline-block">
                        {!! $qrCodeSvg !!}
                    </div>
                </div>

                <div class="bg-gray-50 dark:bg-slate-700/50 rounded-xl p-3 text-center">
                    <p class="text-xs text-gray-400 dark:text-slate-400 mb-1">Manual entry key</p>
                    <p class="font-mono text-sm font-semibold text-gray-700 dark:text-slate-200 tracking-widest">{{ $secret }}</p>
                </div>

                @if($errors->any())
                    <div
                        class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 text-red-700 dark:text-red-300 rounded-xl px-4 py-3 text-sm">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('2fa.enable') }}">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">Confirm
                                with Authentication Code</label>
                            <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
                                   placeholder="000 000" required
                                   class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm text-center tracking-widest outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 dark:focus:ring-amber-500/30 transition">
                        </div>
                        <button type="submit"
                                class="w-full flex items-center justify-center gap-2 bg-amber-500 hover:bg-amber-600 text-slate-900 font-semibold rounded-xl py-3 text-sm transition">
                            <span class="material-icons-round text-lg">verified_user</span> Enable 2FA
                        </button>
                    </div>
                </form>
            @endif

        </x-card>

        <div class="mt-4">
            <a href="{{ route('settings') }}"
               class="text-sm text-gray-400 hover:text-amber-700 dark:hover:text-amber-400 transition flex items-center gap-1">
                <span class="material-icons-round text-base">arrow_back</span> Back to Settings
            </a>
        </div>
    </div>
@endsection
