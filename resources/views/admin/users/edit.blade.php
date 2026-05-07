@extends('layouts.app')
@section('title', 'Edit User')
@section('content')
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.users.index') }}"
           class="text-gray-400 hover:text-gray-600 dark:hover:text-slate-300 transition">
            <span class="material-icons-round text-xl">arrow_back</span>
        </a>
        <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">Edit User</h1>
    </div>
    <form method="POST" action="{{ route('admin.users.update', $user) }}"
          class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm p-6 max-w-lg space-y-5">
        @csrf @method('PUT')
        <div>
            <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">Name *</label>
            <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                   class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition">
            @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">Email *</label>
            <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                   class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition">
            @error('email')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">
                New Password <span class="text-gray-400 dark:text-slate-500">(leave blank to keep current)</span>
            </label>
            <input type="password" name="password" autocomplete="new-password"
                   class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition">
            @error('password')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">Confirm New
                Password</label>
            <input type="password" name="password_confirmation" autocomplete="new-password"
                   class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition">
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">Currency</label>
                <select name="currency_code"
                        class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition">
                    @foreach(['EUR'=>'EUR — Euro','USD'=>'USD — Dollar','GBP'=>'GBP — Pound','CHF'=>'CHF — Franc','CAD'=>'CAD — Canadian $','AUD'=>'AUD — Australian $','JPY'=>'JPY — Yen'] as $code => $label)
                        <option
                            value="{{ $code }}" {{ old('currency_code', $user->currency_code) === $code ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('currency_code')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 dark:text-slate-300 mb-1.5">Locale</label>
                <select name="locale"
                        class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900 transition">
                    <option value="en" {{ old('locale', $user->locale) === 'en' ? 'selected' : '' }}>English</option>
                    <option value="el" {{ old('locale', $user->locale) === 'el' ? 'selected' : '' }}>Greek</option>
                </select>
                @error('locale')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="flex items-center gap-3">
            <input type="checkbox" name="is_admin" id="is_admin" value="1"
                   class="w-4 h-4 text-indigo-600 rounded border-gray-300 dark:border-slate-500 focus:ring-indigo-500"
                {{ old('is_admin', $user->is_admin) ? 'checked' : '' }}>
            <label for="is_admin" class="text-sm font-medium text-gray-700 dark:text-slate-300">Admin user</label>
        </div>
        <div class="flex items-center gap-3 pt-2">
            <button type="submit"
                    class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl px-5 py-2.5 transition">
                <span class="material-icons-round text-lg">save</span> Update User
            </button>
            <a href="{{ route('admin.users.index') }}"
               class="text-sm text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200 transition">Cancel</a>
        </div>
    </form>
@endsection
