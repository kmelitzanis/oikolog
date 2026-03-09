@extends('layouts.app')
@section('title', 'Family')

@section('content')
    <div class="max-w-2xl">
        <h1 class="text-2xl font-extrabold text-gray-900 mb-6">Family</h1>

        @if(!auth()->user()->family_id)

            {{-- No Family --}}
            <div x-data="{ createOpen: false, joinOpen: false }" class="relative">

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 text-center">
                    <span class="material-icons-round text-6xl text-indigo-200 block mb-4">group_add</span>
                    <h2 class="text-xl font-bold text-gray-900 mb-2">No Family Group</h2>
                    <p class="text-sm text-gray-400 max-w-xs mx-auto mb-8">Create a family group to share bills and
                        track expenses together.</p>
                    <div class="flex gap-3 justify-center flex-wrap">
                        <button @click="createOpen=true"
                                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl px-5 py-2.5 transition">
                            <span class="material-icons-round text-lg">add</span> Create Family Group
                    </button>
                        <button @click="joinOpen=true"
                                class="inline-flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold rounded-xl px-5 py-2.5 transition">
                            <span class="material-icons-round text-lg">link</span> Join with Code
                    </button>
                </div>
            </div>

            {{-- Create Modal --}}
                <div x-show="createOpen"
                     x-transition:enter="transition duration-150"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition duration-100"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4"
                     x-cloak>
                    <div class="w-full max-w-sm bg-white rounded-2xl border border-gray-100 shadow-xl p-6"
                         @click.outside="createOpen=false">
                        <h3 class="text-lg font-bold text-gray-900 mb-5">Create Family Group</h3>
                    <form method="POST" action="{{ route('family.create') }}">
                        @csrf
                        <label class="block text-sm font-medium text-gray-600 mb-1.5">Family Name</label>
                        <input type="text" name="name" placeholder="e.g. The Smith Family" required
                               class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition mb-5">
                        <div class="flex gap-3">
                            <button type="submit"
                                    class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl py-2.5 text-sm transition">
                                Create
                            </button>
                            <button type="button" @click="createOpen=false"
                                    class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl py-2.5 text-sm transition">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Join Modal --}}
                <div x-show="joinOpen"
                     x-transition:enter="transition duration-150"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition duration-100"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4"
                     x-cloak>
                    <div class="w-full max-w-sm bg-white rounded-2xl border border-gray-100 shadow-xl p-6"
                         @click.outside="joinOpen=false">
                        <h3 class="text-lg font-bold text-gray-900 mb-5">Join Family Group</h3>
                    <form method="POST" action="{{ route('family.join') }}">
                        @csrf
                        <label class="block text-sm font-medium text-gray-600 mb-1.5">Invite Code</label>
                        <input type="text" name="invite_code" placeholder="e.g. ABCD1234" maxlength="8" required
                               class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition mb-5 uppercase tracking-widest font-bold text-center text-lg">
                        <div class="flex gap-3">
                            <button type="submit"
                                    class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl py-2.5 text-sm transition">
                                Join
                            </button>
                            <button type="button" @click="joinOpen=false"
                                    class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl py-2.5 text-sm transition">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            </div>

        @else

            {{-- Family Info --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-4">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-lg font-bold text-gray-900">{{ $family->name }}</h2>
                    @if(auth()->user()->isFamilyAdmin())
                        <div class="flex items-center gap-2 bg-sky-50 border border-sky-100 rounded-xl px-3 py-2">
                            <span
                                class="text-sm font-bold text-sky-700 tracking-widest">{{ $family->invite_code }}</span>
                            <form method="POST" action="{{ route('family.regenerate') }}">
                                @csrf
                                <button type="submit" title="Regenerate code"
                                        class="text-sky-500 hover:text-sky-700 transition flex">
                                    <span class="material-icons-round text-base">refresh</span>
                                </button>
                            </form>
                        </div>
                    @endif
                </div>

                {{-- Members --}}
                @foreach($family->members as $member)
                    <div class="flex items-center gap-3 py-3 {{ !$loop->last ? 'border-b border-gray-50' : '' }}">
                        <div
                            class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center font-bold text-indigo-700 shrink-0">
                            {{ strtoupper(substr($member->name, 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-semibold text-gray-900">
                                {{ $member->name }}
                                @if($member->id === auth()->id())
                                    <span class="text-xs text-gray-400 font-normal">(you)</span>
                                @endif
                            </div>
                            <div class="text-xs text-gray-400">{{ $member->email }}</div>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                                 {{ $member->family_role === 'owner' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600' }}">
                        {{ ucfirst($member->family_role) }}
                    </span>
                        @if(auth()->user()->isFamilyOwner() && $member->id !== auth()->id())
                            <div class="flex gap-2 shrink-0">
                                <form method="POST" action="{{ route('family.transfer', $member) }}"
                                      onsubmit="return confirm('Transfer ownership to {{ addslashes($member->name) }}?')">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex items-center text-xs font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl px-3 py-1.5 transition">
                                        Transfer
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('family.remove', $member) }}"
                                      onsubmit="return confirm('Remove {{ addslashes($member->name) }} from family?')">
                                @csrf @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex items-center justify-center w-8 h-8 bg-red-50 hover:bg-red-100 text-red-500 rounded-xl transition">
                                        <span class="material-icons-round text-base">person_remove</span>
                                </button>
                            </form>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Leave --}}
            @if(!auth()->user()->isFamilyOwner())
                <form method="POST" action="{{ route('family.leave') }}"
                      onsubmit="return confirm('Leave this family group?')">
                    @csrf @method('DELETE')
                    <button type="submit"
                            class="inline-flex items-center gap-2 bg-red-50 hover:bg-red-100 text-red-600 text-sm font-semibold rounded-xl px-5 py-2.5 transition">
                        <span class="material-icons-round text-lg">exit_to_app</span> Leave Family
                    </button>
                </form>
            @endif

        @endif
    </div>
@endsection
