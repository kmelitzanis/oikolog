@extends('layouts.app')
@section('title', 'Manage Users')
@section('content')
    <div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
        <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">Users</h1>
        <a href="{{ route('admin.users.create') }}"
           class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl px-4 py-2.5 transition">
            <span class="material-icons-round text-lg">add</span> Add User
        </a>
    </div>
    @if(session('success'))
        <div
            class="mb-4 flex items-center gap-2 bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl px-4 py-3">
            <span class="material-icons-round text-base">check_circle</span>
            {{ session('success') }}
        </div>
    @endif
    <div
        class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm overflow-hidden">
        <table class="min-w-full text-sm">
            <thead>
            <tr class="border-b border-gray-100 dark:border-slate-700 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">
                <th class="px-5 py-3">Name</th>
                <th class="px-5 py-3">Email</th>
                <th class="px-5 py-3">Currency</th>
                <th class="px-5 py-3">Locale</th>
                <th class="px-5 py-3">Role</th>
                <th class="px-5 py-3 text-right">Actions</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-slate-700">
            @forelse($users as $user)
                <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/50 transition">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-2.5">
                            <div
                                class="w-8 h-8 rounded-xl bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center text-indigo-600 dark:text-indigo-400 font-bold text-xs shrink-0">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </div>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $user->name }}</span>
                        </div>
                    </td>
                    <td class="px-5 py-3 text-gray-600 dark:text-slate-300">{{ $user->email }}</td>
                    <td class="px-5 py-3 text-gray-600 dark:text-slate-300">{{ $user->currency_code ?? '—' }}</td>
                    <td class="px-5 py-3 text-gray-600 dark:text-slate-300">{{ $user->locale ?? '—' }}</td>
                    <td class="px-5 py-3">
                        @if($user->is_admin)
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700">Admin</span>
                        @else
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">User</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-right">
                        <div class="inline-flex items-center gap-2">
                            <a href="{{ route('admin.users.edit', $user) }}"
                               class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-800 transition">
                                <span class="material-icons-round text-base">edit</span> Edit
                            </a>
                            @if($user->email !== config('app.admin_email', env('ADMIN_EMAIL')))
                                <form action="{{ route('admin.users.destroy', $user) }}" method="POST"
                                      onsubmit="return confirm('Delete user \'{{ addslashes($user->name) }}\'?')">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex items-center gap-1 text-xs font-medium text-red-500 hover:text-red-700 transition">
                                        <span class="material-icons-round text-base">delete</span> Delete
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-5 py-10 text-center text-gray-400 dark:text-slate-500 text-sm">No users
                        found.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
        @if($users->hasPages())
            <div class="px-5 py-4 border-t border-gray-100 dark:border-slate-700">{{ $users->links() }}</div>
        @endif
    </div>
@endsection
