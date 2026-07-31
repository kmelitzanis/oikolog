@extends('layouts.app')
@section('title', __('messages.family'))

{{--
    Family — a build of the `atFamily` panel in mockup 3a.

    A 1.4fr / 1fr split: members table and activity feed on the left, the amber
    invite-code card and shared bills on the right.

    The mockup's "invite by email" button has no feature behind it, so that slot
    carries the app's real second action instead — regenerating the code.
--}}

@section('content')

    @php
        $currency = auth()->user()->currency_code;
        $panel = 'bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-[24px]';
        $sectionLabel = 'text-[0.62rem] font-bold uppercase tracking-[0.09em] text-gray-400 dark:text-slate-500';
        $memberGrid = 'lg:grid lg:grid-cols-[minmax(0,2fr)_1fr_1fr] lg:gap-3.5 lg:items-center';
    @endphp

    {{-- ── Header ─────────────────────────────────────────────────────── --}}
    <div class="flex items-end justify-between gap-5 mb-[22px] flex-wrap">
        <div>
            <div class="text-[1.6rem] font-extrabold tracking-[-0.02em] text-gray-900 dark:text-white leading-tight">
                {{ $family?->name ?? __('messages.family') }}
            </div>
            @if($family)
                <div class="text-[0.82rem] text-gray-400 dark:text-slate-500 mt-[3px]">
                    {{ trans_choice('messages.member_count', $family->members->count(), ['count' => $family->members->count()]) }}
                </div>
            @endif
        </div>
        @if($family && ! auth()->user()->isFamilyOwner())
            <form method="POST" action="{{ route('family.leave') }}"
                  onsubmit="return confirm('{{ addslashes(__('messages.leave_family')) }}?')">
                @csrf @method('DELETE')
                <button type="submit"
                        class="h-10 px-[15px] rounded-xl border border-red-200 dark:border-red-500/30 bg-transparent text-red-600 dark:text-red-400 text-[0.82rem] font-semibold whitespace-nowrap flex items-center gap-2 transition hover:bg-red-50 dark:hover:bg-red-500/10">
                    <span class="material-icons-round text-base">exit_to_app</span>{{ __('messages.leave_family') }}
                </button>
            </form>
        @endif
    </div>

    @if(! $family)

        {{-- ── No family yet ──────────────────────────────────────────── --}}
        <div x-data="{ createOpen: false, joinOpen: false }">
            <div class="{{ $panel }} py-16 px-6 text-center">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-linear-to-br from-amber-500 to-amber-400 flex items-center justify-center">
                    <span class="material-icons-round text-3xl text-slate-900">group_add</span>
                </div>
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ __('messages.no_family') }}</h2>
                <p class="text-sm text-gray-400 dark:text-slate-500 mt-1.5 max-w-md mx-auto">{{ __('messages.no_family_hint') }}</p>
                <div class="mt-6 flex gap-2.5 justify-center flex-wrap">
                    <x-btn type="button" icon="add" @click="createOpen=true">{{ __('messages.create_family') }}</x-btn>
                    <x-btn type="button" variant="outline" icon="link" @click="joinOpen=true">{{ __('messages.join_with_code') }}</x-btn>
                </div>
            </div>

            {{-- Create --}}
            <div x-show="createOpen" x-cloak x-transition.opacity
                 class="fixed inset-0 bg-slate-950/[0.68] backdrop-blur-sm z-50 flex items-center justify-center p-4"
                 @click.self="createOpen=false">
                <div class="w-full max-w-[460px] bg-white dark:bg-slate-800 rounded-[24px] p-6 border border-gray-100 dark:border-slate-700 shadow-[0_30px_70px_rgba(2,6,23,0.6)]">
                    <h3 class="text-[1.05rem] font-bold text-gray-900 dark:text-white mb-4">{{ __('messages.create_family') }}</h3>
                    <form method="POST" action="{{ route('family.create') }}">
                        @csrf
                        <label class="block text-[0.68rem] text-gray-400 dark:text-slate-500 mb-1.5">{{ __('messages.family_name') }}</label>
                        <input type="text" name="name" required
                               class="w-full bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 dark:text-white rounded-2xl px-3.5 py-3 text-sm outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 dark:focus:ring-amber-500/30 transition mb-4">
                        <div class="flex gap-2.5">
                            <x-btn type="button" variant="outline" @click="createOpen=false">{{ __('messages.cancel') }}</x-btn>
                            <x-btn type="submit" block>{{ __('messages.create') }}</x-btn>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Join --}}
            <div x-show="joinOpen" x-cloak x-transition.opacity
                 class="fixed inset-0 bg-slate-950/[0.68] backdrop-blur-sm z-50 flex items-center justify-center p-4"
                 @click.self="joinOpen=false">
                <div class="w-full max-w-[460px] bg-white dark:bg-slate-800 rounded-[24px] p-6 border border-gray-100 dark:border-slate-700 shadow-[0_30px_70px_rgba(2,6,23,0.6)]">
                    <h3 class="text-[1.05rem] font-bold text-gray-900 dark:text-white mb-4">{{ __('messages.join_with_code') }}</h3>
                    <form method="POST" action="{{ route('family.join') }}">
                        @csrf
                        <label class="block text-[0.68rem] text-gray-400 dark:text-slate-500 mb-1.5">{{ __('messages.family_code') }}</label>
                        <input type="text" name="invite_code" required autocapitalize="characters"
                               class="w-full bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 dark:text-white rounded-2xl px-3.5 py-3 text-sm tracking-[0.14em] font-semibold uppercase outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 dark:focus:ring-amber-500/30 transition mb-4">
                        <div class="flex gap-2.5">
                            <x-btn type="button" variant="outline" @click="joinOpen=false">{{ __('messages.cancel') }}</x-btn>
                            <x-btn type="submit" block>{{ __('messages.join_with_code') }}</x-btn>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    @else

        <div class="grid grid-cols-1 lg:grid-cols-[1.4fr_1fr] gap-[18px] items-start">

            {{-- ── Members + activity ─────────────────────────────────── --}}
            <div class="{{ $panel }} overflow-hidden">
                <div class="{{ $memberGrid }} hidden px-5 py-3 bg-gray-50 dark:bg-slate-900/50 text-[0.64rem] font-bold uppercase tracking-[0.07em] text-gray-400 dark:text-slate-500">
                    <div>{{ __('messages.member') }}</div>
                    <div>{{ __('messages.role') }}</div>
                    <div class="text-right">{{ __('messages.actions') }}</div>
                </div>

                @foreach($family->members as $member)
                    @php $isOwner = $member->family_role === 'owner'; @endphp
                    <div class="flex items-center gap-3 {{ $memberGrid }} px-4 lg:px-5 py-3.5 border-t border-gray-100 dark:border-slate-700/60">
                        {{-- 1 · Member --}}
                        <div class="flex items-center gap-3 flex-1 min-w-0 lg:flex-none">
                            @include('partials.avatar', ['user' => $member, 'rounded' => 'rounded-xl', 'size' => 'w-[38px] h-[38px]'])
                            <div class="min-w-0">
                                <div class="text-[0.88rem] font-semibold text-gray-900 dark:text-white truncate">
                                    {{ $member->name }}
                                    @if($member->id === auth()->id())
                                        <span class="text-[0.7rem] font-normal text-gray-400 dark:text-slate-500">({{ __('messages.you') }})</span>
                                    @endif
                                </div>
                                <div class="text-[0.7rem] text-gray-400 dark:text-slate-500 truncate mt-px">{{ $member->email }}</div>
                            </div>
                        </div>

                        {{-- 2 · Role --}}
                        <div>
                            <span class="inline-flex px-2.5 py-1 rounded-full text-[0.72rem] font-semibold
                                {{ $isOwner
                                    ? 'bg-amber-500/[0.16] text-amber-700 dark:text-amber-300'
                                    : 'bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-slate-300' }}">
                                {{ $isOwner ? __('messages.owner') : __('messages.member') }}
                            </span>
                        </div>

                        {{-- 3 · Actions --}}
                        <div class="flex items-center justify-end gap-2 shrink-0">
                            @if(auth()->user()->isFamilyOwner() && $member->id !== auth()->id())
                                <form method="POST" action="{{ route('family.transfer', $member) }}"
                                      onsubmit="return confirm('{{ addslashes(__('messages.transfer')) }} → {{ addslashes($member->name) }}?')">
                                    @csrf
                                    <button type="submit"
                                            class="h-8 px-3 rounded-xl bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 text-gray-600 dark:text-slate-300 text-[0.72rem] font-semibold transition">
                                        {{ __('messages.transfer') }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('family.remove', $member) }}"
                                      onsubmit="return confirm('{{ addslashes(__('messages.remove_member')) }} — {{ addslashes($member->name) }}?')">
                                    @csrf @method('DELETE')
                                    <x-icon-btn tone="danger" icon="person_remove" type="submit"
                                                title="{{ __('messages.remove_member') }}" />
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach

                {{-- Recent activity --}}
                <div class="px-5 py-4 border-t border-gray-100 dark:border-slate-700/60">
                    <div class="{{ $sectionLabel }} mb-2.5">{{ __('messages.recent_activity') }}</div>
                    @forelse($activity as $entry)
                        @php $isPaid = $entry['type'] === 'paid'; @endphp
                        <div class="flex gap-[11px] py-2 {{ $loop->first ? '' : 'border-t border-gray-100 dark:border-slate-700/60' }}">
                            <span class="w-2 h-2 rounded-full mt-[5px] shrink-0 {{ $isPaid ? 'bg-emerald-400' : 'bg-amber-400' }}"></span>
                            <div class="flex-1 min-w-0">
                                <div class="text-[0.82rem] font-medium text-gray-600 dark:text-slate-300">
                                    {{ __($isPaid ? 'messages.activity_paid' : 'messages.activity_added', ['actor' => $entry['actor'] ?? '—']) }}
                                    <strong class="text-gray-900 dark:text-white">{{ $entry['subject'] }}</strong>
                                </div>
                                <div class="text-[0.68rem] text-gray-400 dark:text-slate-600 mt-px">
                                    {{ $entry['at']->diffForHumans() }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400 dark:text-slate-500 py-4">{{ __('messages.no_activity') }}</p>
                    @endforelse
                </div>
            </div>

            {{-- ── Invite code + shared bills ─────────────────────────── --}}
            <div>
                @if(auth()->user()->isFamilyAdmin())
                    <div x-data="{ copied: false }"
                         class="rounded-[24px] bg-amber-500 p-[22px] mb-4">
                        <div class="text-[0.66rem] font-semibold uppercase tracking-[0.09em] text-slate-900/70">
                            {{ __('messages.invite_code') }}
                        </div>
                        <div class="text-[1.9rem] font-extrabold tracking-[0.14em] text-slate-900 mt-1.5 break-all">
                            {{ $family->invite_code }}
                        </div>
                        <div class="flex gap-[9px] mt-4">
                            <button type="button"
                                    @click="navigator.clipboard.writeText('{{ $family->invite_code }}'); copied = true; setTimeout(() => copied = false, 1600)"
                                    class="flex-1 h-[42px] rounded-xl bg-slate-900/[0.14] hover:bg-slate-900/25 text-slate-900 text-[0.82rem] font-semibold transition">
                                <span x-text="copied ? '{{ __('messages.copied') }}' : '{{ __('messages.copy') }}'"></span>
                            </button>
                            {{-- The mockup's second slot is "invite by email"; the
                                 app has no such feature, so it carries the real
                                 action that exists here. --}}
                            <form method="POST" action="{{ route('family.regenerate') }}" class="flex-1">
                                @csrf
                                <button type="submit"
                                        class="w-full h-[42px] rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-[0.82rem] font-semibold transition">
                                    {{ __('messages.regenerate_code') }}
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

                <div class="{{ $panel }} px-5 py-[18px]">
                    <div class="{{ $sectionLabel }} mb-3">{{ __('messages.shared_bills') }}</div>
                    @forelse($sharedBills as $bill)
                        <div class="flex justify-between gap-3 py-2.5 border-t border-gray-100 dark:border-slate-700/60 text-[0.82rem] font-medium">
                            <span class="text-gray-600 dark:text-slate-300 truncate">{{ $bill->name }}</span>
                            <span class="text-gray-900 dark:text-white shrink-0">
                                {{ $bill->currency_code ?? $currency }} {{ number_format($bill->amount, 2) }}
                            </span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400 dark:text-slate-500 py-4 text-center">{{ __('messages.no_shared_bills') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

    @endif

@endsection
