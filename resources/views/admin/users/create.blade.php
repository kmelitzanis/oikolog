@extends('layouts.app')
@section('title', 'Add User')
@section('content')
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.users.index') }}" class="text-gray-400 hover:text-gray-600 transition">
            <span class="material-icons-round text-xl">arrow_back</span>
        </a>
        <h1 class="text-2xl font-extrabold text-gray-900">Add User</h1>
    </div>
    <form method="POST" action="{{ route('admin.users.store') }}"
          class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 max-w-lg space-y-5">
        @csrf
        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1.5">Name *</label>
            <input type="text" name="name" value="{{ old('name') }}" required
                   class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition">
            @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1.5">Email *</label>
            <input type="email" name="email" value="{{ old('email') }}" required
                   class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition">
            @error('email')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1.5">Password *</label>
            <input type="password" name="password" required autocomplete="new-password"
                   class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition">
            @error('password')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1.5">Confirm Password *</label>
            <input type="password" name="password_confirmation" required autocomplete="new-password"
                   class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition">
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5">Currency</label>
                <select name="currency_code"
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition">
                    @foreach(['EUR'=>'EUR — Euro','USD'=>'USD — Dollar','GBP'=>'GBP — Pound','CHF'=>'CHF — Franc','CAD'=>'CAD — Canadian $','AUD'=>'AUD — Australian $','JPY'=>'JPY — Yen'] as $code => $label)
                        <option
                            value="{{ $code }}" {{ old('currency_code', 'EUR') === $code ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('currency_code')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5">Locale</label>
                <select name="locale"
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition">
                    <option value="en" {{ old('locale', 'en') === 'en' ? 'selected' : '' }}>English</option>
                    <option value="el" {{ old('locale') === 'el' ? 'selected' : '' }}>Greek</option>
                </select>
                @error('locale')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="flex items-center gap-3">
            <div class="flex items-center justify-between bg-gray-50 dark:bg-slate-700/50 rounded-xl px-4 py-3">
                <label for="is_admin" class="text-sm font-medium text-gray-700 dark:text-slate-300">Admin user</label>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="is_admin" id="is_admin" value="1"
                           class="sr-only peer"
                        {{ old('is_admin') ? 'checked' : '' }}>
                    <div class="w-11 h-6 bg-gray-200 dark:bg-slate-600 peer-focus:outline-none rounded-full peer
                                peer-checked:after:translate-x-full peer-checked:after:border-white
                                after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all
                                 peer-checked:bg-emerald-500"></div>
                </label>
            </div>
        </div>
        <div class="flex items-center gap-3 pt-2">
            <button type="submit"
                    class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-slate-900 text-sm font-semibold rounded-xl px-5 py-2.5 transition">
                <span class="material-icons-round text-lg">person_add</span> Create User
            </button>
            <a href="{{ route('admin.users.index') }}"
               class="text-sm text-gray-500 hover:text-gray-700 transition">Cancel</a>
        </div>
    </form>
@endsection
