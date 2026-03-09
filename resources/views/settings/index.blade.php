@extends('layouts.app')
@section('title', 'Settings')

@section('content')
    <div class="max-w-xl">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-extrabold text-gray-900">Settings</h1>
        </div>

        <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data">
            @csrf
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-5">

                {{-- Avatar --}}
                <div class="flex items-center gap-4">
                    <div
                        class="w-16 h-16 rounded-2xl overflow-hidden bg-indigo-50 flex items-center justify-center shrink-0">
                        @php $avatar = method_exists($user, 'avatarUrl') ? $user->avatarUrl() : $user->avatar_url; @endphp
                        @if($avatar)
                            <img src="{{ $avatar }}" alt="avatar" class="w-full h-full object-cover">
                        @else
                            <span
                                class="text-xl font-extrabold text-indigo-600">{{ strtoupper(substr($user->name,0,1)) }}</span>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <label class="block text-sm font-medium text-gray-600 mb-1.5">Avatar</label>
                        <input type="file" name="avatar" accept="image/*"
                               class="w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                        <input type="url" name="avatar_url" value="{{ old('avatar_url', $user->avatar_url) }}"
                               placeholder="Or paste image URL…"
                               class="mt-2 w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition">
                    </div>
                </div>

                <hr class="border-gray-100">

                {{-- Name --}}
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1.5">Name</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                           class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition">
                </div>

                {{-- Email --}}
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1.5">Email</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                           class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition">
                </div>

                {{-- Currency --}}
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1.5">Currency</label>
                    <select name="currency_code"
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition">
                        @foreach(['EUR'=>'€ EUR — Euro','USD'=>'$ USD — US Dollar','GBP'=>'£ GBP — British Pound','CHF'=>'Fr CHF — Swiss Franc','CAD'=>'CA$ CAD — Canadian Dollar','AUD'=>'A$ AUD — Australian Dollar'] as $code=>$label)
                            <option
                                value="{{ $code }}" {{ old('currency_code',$user->currency_code)===$code?'selected':'' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Language --}}
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1.5">Language</label>
                    <select name="locale"
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition">
                        @php
                            $labels = ['en' => 'English', 'el' => 'Ελληνικά'];
                            $current = old('locale', $user->locale ?? app()->getLocale() ?? 'en');
                        @endphp
                        @foreach($availableLocales ?? ['en'] as $loc)
                            <option
                                value="{{ $loc }}" {{ $current === $loc ? 'selected' : '' }}>{{ $labels[$loc] ?? strtoupper($loc) }}</option>
                        @endforeach
                    </select>
                </div>

                <hr class="border-gray-100">

                {{-- Password --}}
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1.5">New Password <span
                            class="text-gray-400">(leave blank to keep)</span></label>
                    <input type="password" name="password" autocomplete="new-password"
                           class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1.5">Confirm Password</label>
                    <input type="password" name="password_confirmation" autocomplete="new-password"
                           class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition">
                </div>

                {{-- Submit --}}
                <div class="flex gap-3 pt-2">
                    <button type="submit"
                            class="flex-1 flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl py-3 text-sm transition">
                        <span class="material-icons-round text-lg">save</span> Save Changes
                    </button>
                    <a href="{{ route('dashboard') }}"
                       class="flex items-center justify-center px-5 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl text-sm transition">
                        Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>
@endsection

