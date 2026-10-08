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
                        <div class="text-xs text-gray-400 dark:text-slate-500">{{ __('messages.avatar_hint') }}</div>
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

                    {{-- Gender — Greek copy inflects the article before a name --}}
                    <div class="mt-5">
                        <label class="{{ $label }}">{{ __('messages.gender') }}</label>
                        <div class="flex gap-2">
                            @php $currentGender = old('gender', $user->gender); @endphp
                            @foreach(['male' => ['man', __('messages.gender_male')], 'female' => ['woman', __('messages.gender_female')]] as $g => [$gicon, $glabel])
                                <label class="flex-1 cursor-pointer">
                                    <input type="radio" name="gender" value="{{ $g }}" class="peer sr-only"
                                           {{ $currentGender === $g ? 'checked' : '' }}>
                                    <span class="flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium border
                                                 border-gray-200 dark:border-slate-600 text-gray-600 dark:text-slate-400
                                                 peer-checked:border-amber-500 peer-checked:bg-amber-50 dark:peer-checked:bg-amber-500/15
                                                 peer-checked:text-amber-700 dark:peer-checked:text-amber-300 transition">
                                        <span class="material-icons-round text-base">{{ $gicon }}</span> {{ $glabel }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <div class="text-xs text-gray-400 dark:text-slate-500 mt-1.5">{{ __('messages.gender_hint') }}</div>
                    </div>
                </x-card>

                {{-- Password --}}
                <x-card flush class="p-6">
                    <div class="{{ $cardTitle }}">{{ __('messages.security') }}</div>

                    <div>
                        <label for="current_password" class="{{ $label }}">{{ __('messages.current_password') }}</label>
                        <input type="password" id="current_password" name="current_password" autocomplete="current-password"
                               class="{{ $input }}">
                        <div class="text-xs text-gray-400 dark:text-slate-500 mt-1.5">{{ __('messages.current_password_hint') }}</div>
                    </div>
                    <div class="mt-5">
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

                    {{-- Default account — what the pay modal opens on --}}
                    <div class="mt-5">
                        <label class="{{ $label }}">{{ __('messages.default_account') }}</label>
                        <select name="default_account_id" class="{{ $input }}">
                            <option value="">{{ __('messages.no_default_account') }}</option>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}"
                                    @selected(old('default_account_id', $user->default_account_id) === $acc->id)>{{ $acc->name }}</option>
                            @endforeach
                        </select>
                        <div class="text-xs text-gray-400 dark:text-slate-500 mt-1.5">{{ __('messages.default_account_hint') }}</div>
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

                {{-- Push notifications. Permission can only be requested from a
                     user gesture, so this is a live toggle rather than a field
                     the Save button submits. --}}
                <x-card flush class="p-6" x-data="pushToggle()" x-init="init()">
                    <div class="{{ $cardTitle }}">{{ __('messages.notifications') }}</div>

                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-800 dark:text-white">{{ __('messages.push_notifications') }}</p>
                            <p class="text-xs text-gray-400 dark:text-slate-400 mt-0.5">{{ __('messages.push_notifications_hint') }}</p>
                            <p x-show="blocked" x-cloak class="text-xs text-red-500 mt-1.5">{{ __('messages.push_blocked') }}</p>
                            <p x-show="state === 'unavailable'" x-cloak class="text-xs text-gray-400 dark:text-slate-500 mt-1.5">
                                {{ __('messages.push_unavailable') }}
                            </p>
                            {{-- The commonest reason this toggle does nothing is
                                 plain http, which no browser allows push on. --}}
                            <p x-show="state === 'insecure'" x-cloak class="text-xs text-amber-600 dark:text-amber-400 mt-1.5">
                                {{ __('messages.push_needs_https') }}
                            </p>
                        </div>
                        <button type="button" @click="toggle()"
                                :disabled="busy || blocked || state === 'unavailable' || state === 'insecure' || state === 'unknown'"
                                :aria-pressed="enabled ? 'true' : 'false'"
                                class="relative shrink-0 w-11 h-6 rounded-full transition disabled:opacity-40 disabled:cursor-not-allowed"
                                :class="enabled ? 'bg-emerald-500' : 'bg-gray-200 dark:bg-slate-600'">
                            <span class="absolute top-[2px] left-[2px] w-5 h-5 bg-white rounded-full transition-transform"
                                  :class="enabled ? 'translate-x-5' : ''"></span>
                        </button>
                    </div>
                </x-card>

                {{-- Invoice mail. A separate form: it carries a password and
                     must not ride along with the profile save.

                     The conditional wraps the whole card rather than sitting
                     inside its slot: a Blade @if that straddles a component's
                     slot boundary compiles to an unbalanced endif. --}}
                @if($mailboxReady ?? true)
                <x-card flush class="p-6">
                    <div class="{{ $cardTitle }}">{{ __('messages.invoice_mail') }}</div>
                    <p class="text-xs text-gray-400 dark:text-slate-400 -mt-2 mb-4">{{ __('messages.mailbox_hint') }}</p>

                    @error('mailbox')
                        <p class="mb-3 text-xs text-red-500">{{ $message }}</p>
                    @enderror

                    {{-- Two ways in, one per provider. Microsoft only takes OAuth;
                         Gmail takes an app password, which (unlike Google OAuth for
                         an unverified app) never expires. Host, port and folder
                         are filled in server-side, so the user types only what
                         they actually know. --}}
                    @php($microsoftReady = app(\App\Services\MicrosoftMailAuth::class)->isConfigured())

                    @if($mailbox?->exists)
                        <div class="rounded-xl border border-emerald-200 dark:border-emerald-800/60 bg-emerald-50 dark:bg-emerald-900/20 p-3 flex items-center gap-3">
                            @if($mailbox->usesMicrosoft())
                                <svg width="20" height="20" viewBox="0 0 21 21" aria-hidden="true" class="shrink-0">
                                    <rect x="1" y="1" width="9" height="9" fill="#f25022"/><rect x="11" y="1" width="9" height="9" fill="#7fba00"/>
                                    <rect x="1" y="11" width="9" height="9" fill="#00a4ef"/><rect x="11" y="11" width="9" height="9" fill="#ffb900"/>
                                </svg>
                            @else
                                <span class="material-icons-round text-red-500 shrink-0">mail</span>
                            @endif
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                    {{ __('messages.mailbox_connected_as', ['email' => $mailbox->username]) }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-slate-400">
                                    @if($mailbox->last_scanned_at)
                                        {{ __('messages.last_scanned', ['when' => $mailbox->last_scanned_at->diffForHumans()]) }}
                                    @else
                                        {{ $mailbox->usesMicrosoft() ? 'Microsoft' : 'Gmail' }}
                                    @endif
                                </div>
                            </div>
                            <form method="POST" action="{{ route('mailbox.disconnect') }}"
                                  onsubmit="return confirm({{ Illuminate\Support\Js::from(__('messages.mailbox_disconnect') . '?') }})">
                                @csrf
                                <x-btn variant="ghost" size="sm" type="submit" icon="link_off">{{ __('messages.mailbox_disconnect') }}</x-btn>
                            </form>
                        </div>

                        @if($mailbox->last_error)
                            <p class="text-xs text-red-500 mt-2">{{ $mailbox->last_error }}</p>
                        @endif

                        <div class="grid grid-cols-2 gap-2 mt-3">
                            <x-btn form="mailbox-test-form" type="submit" variant="outline" icon="wifi_tethering">
                                {{ __('messages.test_connection') }}
                            </x-btn>
                            <x-btn form="mailbox-scan-form" type="submit" variant="outline" icon="sync">
                                {{ __('messages.scan_now') }}
                            </x-btn>
                        </div>
                    @else
                        <div x-data="{ gmail: {{ $errors->has('username') || $errors->has('password') ? 'true' : 'false' }} }" class="space-y-2">
                            @if($microsoftReady)
                                <a href="{{ route('mailbox.microsoft.connect') }}"
                                   class="w-full h-11 rounded-xl border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800 hover:bg-gray-50 dark:hover:bg-slate-700 flex items-center justify-center gap-2.5 text-sm font-semibold text-gray-700 dark:text-slate-200 transition">
                                    <svg width="16" height="16" viewBox="0 0 21 21" aria-hidden="true">
                                        <rect x="1" y="1" width="9" height="9" fill="#f25022"/><rect x="11" y="1" width="9" height="9" fill="#7fba00"/>
                                        <rect x="1" y="11" width="9" height="9" fill="#00a4ef"/><rect x="11" y="11" width="9" height="9" fill="#ffb900"/>
                                    </svg>
                                    {{ __('messages.mailbox_microsoft_connect') }}
                                </a>
                            @endif

                            <button type="button" @click="gmail = !gmail"
                                    class="w-full h-11 rounded-xl border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800 hover:bg-gray-50 dark:hover:bg-slate-700 flex items-center justify-center gap-2.5 text-sm font-semibold text-gray-700 dark:text-slate-200 transition">
                                <span class="material-icons-round text-red-500 text-lg">mail</span>
                                {{ __('messages.mailbox_gmail_connect') }}
                            </button>

                            <div x-show="gmail" x-cloak class="rounded-xl border border-gray-100 dark:border-slate-700 p-3 space-y-3">
                                <div>
                                    <label class="{{ $label }}">{{ __('messages.email') }}</label>
                                    <input form="mailbox-form" type="email" name="username" autocomplete="off"
                                           placeholder="you@gmail.com" value="{{ old('username') }}" class="{{ $input }}">
                                    @error('username') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="{{ $label }}">{{ __('messages.app_password') }}</label>
                                    {{-- Never rendered back to the browser. --}}
                                    <input form="mailbox-form" type="password" name="password" autocomplete="new-password" class="{{ $input }}">
                                    @error('password') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                                    <p class="text-xs text-gray-400 dark:text-slate-500 mt-1.5">{{ __('messages.mailbox_gmail_app_password_hint') }}</p>
                                </div>
                                <x-btn form="mailbox-form" type="submit" icon="link" class="w-full">
                                    {{ __('messages.mailbox_gmail_save') }}
                                </x-btn>
                            </div>
                        </div>
                    @endif
                </x-card>
                @else
                {{-- The table is missing, so the form has nowhere to save to.
                     Say why rather than showing dead fields. --}}
                <x-card flush class="p-6">
                    <div class="{{ $cardTitle }}">{{ __('messages.invoice_mail') }}</div>
                    <p class="text-xs text-amber-600 dark:text-amber-400">{{ __('messages.mailbox_needs_migration') }}</p>
                </x-card>
                @endif

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

    {{-- Targets for the form="" attributes above. A form cannot be nested
         inside another, and the profile form wraps the whole grid. --}}
    <form id="mailbox-form" method="POST" action="{{ route('mailbox.update') }}" class="hidden">@csrf</form>
    <form id="mailbox-test-form" method="POST" action="{{ route('mailbox.test') }}" class="hidden">@csrf</form>
    <form id="mailbox-scan-form" method="POST" action="{{ route('mailbox.scan') }}" class="hidden">@csrf</form>
@endsection
