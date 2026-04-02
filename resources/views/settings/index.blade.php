@extends('layouts.app')
@section('title', __('messages.settings'))
@section('content')
    <div class="max-w-xl">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">{{ __('messages.settings') }}</h1>
        </div>
        <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data">
            @csrf
            <div
                class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm p-6 space-y-5">
                {{-- Avatar via FilePond --}}
                <div>
                    <label
                        class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-2">{{ __('messages.avatar') }}</label>
                    <div class="flex items-center gap-4 mb-3">
                        <div
                            class="w-14 h-14 rounded-2xl overflow-hidden bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center shrink-0">
                        @php $avatar = method_exists($user, 'avatarUrl') ? $user->avatarUrl() : $user->avatar_url; @endphp
                        @if($avatar)
                            <img src="{{ $avatar }}" alt="avatar" class="w-full h-full object-cover">
                        @else
                                <span
                                    class="text-xl font-extrabold text-indigo-600 dark:text-indigo-400">{{ strtoupper(substr($user->name,0,1)) }}</span>
                        @endif
                    </div>
                        <div class="text-xs text-gray-400 dark:text-slate-500">{{ __('messages.avatar') }} — JPG, PNG,
                            WebP, max 2 MB
                        </div>
                    </div>
                    <input type="file" name="avatar" data-filepond="avatar" accept="image/*">
                </div>
                <hr class="border-gray-100 dark:border-slate-700">
                {{-- Name --}}
                <div>
                    <label
                        class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">{{ __('messages.name') }}</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                           class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition">
                </div>
                {{-- Email --}}
                <div>
                    <label
                        class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">{{ __('messages.email') }}</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                           class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition">
                </div>
                {{-- Currency --}}
                <div>
                    <label
                        class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">{{ __('messages.currency') }}</label>
                    <select name="currency_code"
                            class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition">
                        @foreach(['EUR'=>'€ EUR — Euro','USD'=>'$ USD — US Dollar','GBP'=>'£ GBP — British Pound','CHF'=>'Fr CHF — Swiss Franc','CAD'=>'CA$ CAD — Canadian Dollar','AUD'=>'A$ AUD — Australian Dollar'] as $code=>$label)
                            <option
                                value="{{ $code }}" {{ old('currency_code',$user->currency_code)===$code?'selected':'' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- Language --}}
                <div>
                    <label
                        class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">{{ __('messages.language') }}</label>
                    <select name="locale"
                            class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition">
                        @php
                            $labels  = ['en' => 'English', 'el' => 'Ελληνικά'];
                            $current = old('locale', $user->locale ?? app()->getLocale() ?? 'en');
                        @endphp
                        @foreach($availableLocales ?? ['en'] as $loc)
                            <option
                                value="{{ $loc }}" {{ $current === $loc ? 'selected' : '' }}>{{ $labels[$loc] ?? strtoupper($loc) }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- Theme --}}
                <div x-data="{ isDark: document.documentElement.classList.contains('dark') }">
                    <label
                        class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-2">{{ __('messages.theme') }}</label>
                    <div class="flex gap-2">
                        @foreach(['light' => ['light_mode', __('messages.light')], 'dark' => ['dark_mode', __('messages.dark')]] as $t => [$icon, $tlabel])
                            <button type="button"
                                    @click="isDark = '{{ $t }}' === 'dark'; document.documentElement.classList.toggle('dark', isDark); localStorage.setItem('theme', isDark ? 'dark' : 'light')"
                                    :class="(isDark === ('{{ $t }}' === 'dark')) ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300' : 'border-gray-200 dark:border-slate-600 text-gray-600 dark:text-slate-400'"
                                    class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium border transition">
                                <span class="material-icons-round text-base">{{ $icon }}</span> {{ $tlabel }}
                            </button>
                        @endforeach
                    </div>
                </div>
                <hr class="border-gray-100 dark:border-slate-700">
                {{-- Password --}}
                <div>
                    <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">
                        {{ __('messages.new_password') }} <span class="text-gray-400 dark:text-slate-500">({{ __('messages.leave_blank') }})</span>
                    </label>
                    <input type="password" name="password" autocomplete="new-password"
                           class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition">
                </div>
                <div>
                    <label
                        class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">{{ __('messages.confirm_password') }}</label>
                    <input type="password" name="password_confirmation" autocomplete="new-password"
                           class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition">
                </div>
                {{-- Submit --}}
                <div class="flex gap-3 pt-2">
                    <button type="submit"
                            class="flex-1 flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl py-3 text-sm transition">
                        <span class="material-icons-round text-lg">save</span> {{ __('messages.save_changes') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection
