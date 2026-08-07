@extends('layouts.app')
@section('title', __('messages.settings'))
@section('content')
    @php
        // One shared recipe for the inputs on this page — they were repeated
        // verbatim six times before.
        $input = 'w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 dark:focus:ring-amber-500/30 transition';
        $label = 'block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5';
        $cardTitle = 'text-[0.7rem] font-bold uppercase tracking-[0.09em] text-gray-400 dark:text-slate-500 mb-4';
    @endphp

    <div class="max-w-5xl">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">{{ __('messages.settings') }}</h1>
        </div>

        {{-- Two columns from lg up, a single stack below. The form *is* the grid so
             one Save button still submits every field. --}}
        <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data"
              class="grid grid-cols-1 lg:grid-cols-2 gap-[18px] items-start">
            @csrf

            {{-- ── Left column: who you are ──────────────────────────────── --}}
            <div class="space-y-[18px]">
                <x-card flush class="p-6">
                    <div class="{{ $cardTitle }}">{{ __('messages.profile') }}</div>

                    {{-- Avatar --}}
                    <div>
                        <label class="{{ $label }}">{{ __('messages.avatar') }}</label>
                        <div id="avatar-drop-area"
                             class="w-full flex flex-col items-center justify-center border-2 border-dashed border-gray-300 dark:border-slate-600 rounded-xl p-4 bg-gray-50 dark:bg-slate-700 cursor-pointer transition hover:border-amber-400 mb-2">
                            <span id="avatar-drop-text"
                                  class="text-gray-400 dark:text-slate-500 text-sm mb-2">{{ __('messages.drag_and_drop_avatar') }}</span>
                            <input id="avatar-input" type="file" name="avatar" accept="image/*" class="hidden">
                            <img id="avatar-preview" src="{{ $avatar ?? '' }}" alt="Avatar preview"
                                 class="{{ $avatar ? '' : 'hidden' }} w-14 h-14 object-cover rounded-2xl border border-gray-100 dark:border-slate-600 bg-white dark:bg-slate-700 p-1 mt-2">
                        </div>
                        <div class="text-xs text-gray-400 dark:text-slate-500">JPG, PNG, WebP — max 2 MB</div>
                    </div>

                    {{-- Name --}}
                    <div class="mt-5">
                        <label class="{{ $label }}">{{ __('messages.name') }}</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                               class="{{ $input }}">
                    </div>

                    {{-- Email --}}
                    <div class="mt-5">
                        <label class="{{ $label }}">{{ __('messages.email') }}</label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                               class="{{ $input }}">
                    </div>
                </x-card>

                {{-- Password --}}
                <x-card flush class="p-6">
                    <div class="{{ $cardTitle }}">{{ __('messages.security') }}</div>

                    <div>
                        <label class="{{ $label }}">
                            {{ __('messages.new_password') }}
                            <span class="text-gray-400 dark:text-slate-500 font-normal">({{ __('messages.leave_blank') }})</span>
                        </label>
                        <input type="password" name="password" autocomplete="new-password" class="{{ $input }}">
                    </div>
                    <div class="mt-5">
                        <label class="{{ $label }}">{{ __('messages.confirm_password') }}</label>
                        <input type="password" name="password_confirmation" autocomplete="new-password"
                               class="{{ $input }}">
                    </div>
                </x-card>
            </div>

            {{-- ── Right column: how the app behaves ─────────────────────── --}}
            <div class="space-y-[18px]">
                <x-card flush class="p-6">
                    <div class="{{ $cardTitle }}">{{ __('messages.preferences') }}</div>

                    {{-- Currency --}}
                    <div>
                        <label class="{{ $label }}">{{ __('messages.currency') }}</label>
                        <select name="currency_code" class="{{ $input }}">
                            @foreach(['EUR'=>'€ EUR — Euro','USD'=>'$ USD — US Dollar','GBP'=>'£ GBP — British Pound','CHF'=>'Fr CHF — Swiss Franc','CAD'=>'CA$ CAD — Canadian Dollar','AUD'=>'A$ AUD — Australian Dollar'] as $code=>$labelText)
                                <option value="{{ $code }}" {{ old('currency_code',$user->currency_code)===$code?'selected':'' }}>{{ $labelText }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Language --}}
                    <div class="mt-5">
                        <label class="{{ $label }}">{{ __('messages.language') }}</label>
                        <select name="locale" class="{{ $input }}">
                            @php
                                $labels  = ['en' => 'English', 'el' => 'Ελληνικά'];
                                $current = old('locale', $user->locale ?? app()->getLocale() ?? 'en');
                            @endphp
                            @foreach($availableLocales ?? ['en'] as $loc)
                                <option value="{{ $loc }}" {{ $current === $loc ? 'selected' : '' }}>{{ $labels[$loc] ?? strtoupper($loc) }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Theme --}}
                    <div class="mt-5" x-data="{ isDark: document.documentElement.classList.contains('dark') }">
                        <label class="{{ $label }}">{{ __('messages.theme') }}</label>
                        <div class="flex gap-2">
                            @foreach(['light' => ['light_mode', __('messages.light')], 'dark' => ['dark_mode', __('messages.dark')]] as $t => [$icon, $tlabel])
                                <button type="button"
                                        @click="isDark = '{{ $t }}' === 'dark'; document.documentElement.classList.toggle('dark', isDark); localStorage.setItem('theme', isDark ? 'dark' : 'light')"
                                        :class="(isDark === ('{{ $t }}' === 'dark')) ? 'border-amber-500 bg-amber-50 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300' : 'border-gray-200 dark:border-slate-600 text-gray-600 dark:text-slate-400'"
                                        class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium border transition">
                                    <span class="material-icons-round text-base">{{ $icon }}</span> {{ $tlabel }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </x-card>

                {{-- 2FA — a link out, not a field, but it belongs beside the
                     password box rather than in a stray card below the form. --}}
                <x-card flush class="p-6">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-xl {{ $user->two_factor_enabled ? 'bg-green-50 dark:bg-green-900/30' : 'bg-amber-50 dark:bg-amber-900/30' }} flex items-center justify-center shrink-0">
                                <span class="material-icons-round {{ $user->two_factor_enabled ? 'text-green-600 dark:text-green-400' : 'text-amber-500' }}">
                                    {{ $user->two_factor_enabled ? 'verified_user' : 'shield' }}
                                </span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-800 dark:text-white">{{ __('messages.two_factor_auth') }}</p>
                                <p class="text-xs text-gray-400 dark:text-slate-400">
                                    {{ $user->two_factor_enabled ? __('messages.two_factor_on') : __('messages.two_factor_off') }}
                                </p>
                            </div>
                        </div>
                        <a href="{{ route('2fa.setup') }}"
                           class="shrink-0 text-sm font-medium text-amber-700 hover:text-amber-600 dark:text-amber-400 dark:hover:text-amber-300 transition">
                            {{ $user->two_factor_enabled ? __('messages.manage') : __('messages.set_up') }}
                        </a>
                    </div>
                </x-card>
            </div>

            {{-- Save spans both columns so it reads as one action for the page --}}
            <div class="lg:col-span-2">
                <button type="submit"
                        class="w-full sm:w-auto sm:min-w-[14rem] flex items-center justify-center gap-2 bg-amber-500 hover:bg-amber-600 text-slate-900 font-semibold rounded-xl px-6 py-3 text-sm transition">
                    <span class="material-icons-round text-lg">save</span> {{ __('messages.save_changes') }}
                </button>
            </div>
        </form>
    </div>
@endsection
