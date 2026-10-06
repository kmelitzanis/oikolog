@extends('layouts.app')
@section('title', __('messages.translations'))

@section('content')
    <div class="max-w-5xl">
        <x-page-header :title="__('messages.translations')" :subtitle="__('messages.translations_hint')">
            <x-slot:actions>
                <x-btn :href="route('translations.create')" icon="add">{{ __('messages.add') }}</x-btn>
            </x-slot:actions>
        </x-page-header>

        <x-card flush class="overflow-hidden">
            @forelse($translations as $t)
                <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 px-4 sm:px-5 py-3.5 {{ ! $loop->first ? 'border-t border-gray-100 dark:border-slate-700' : '' }}">
                    <div class="flex items-center gap-2 shrink-0 sm:w-44">
                        <span class="px-2 py-0.5 rounded-md bg-amber-500/15 text-amber-700 dark:text-amber-400 text-[0.68rem] font-bold uppercase">{{ $t->locale }}</span>
                        <span class="text-xs text-gray-400 dark:text-slate-500 truncate">{{ $t->group }}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="font-mono text-xs text-gray-500 dark:text-slate-400 break-all">{{ $t->key }}</div>
                        <div class="text-sm text-gray-900 dark:text-white mt-0.5 break-words">{{ \Illuminate\Support\Str::limit($t->value, 160) }}</div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <x-btn variant="ghost" size="sm" icon="edit" :href="route('translations.edit', $t)">{{ __('messages.edit') }}</x-btn>
                        <form method="POST" action="{{ route('translations.destroy', $t) }}"
                              onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('messages.confirm_delete')) }})">
                            @csrf @method('DELETE')
                            <x-btn variant="danger" size="sm" icon="delete">{{ __('messages.delete') }}</x-btn>
                        </form>
                    </div>
                </div>
            @empty
                <x-empty-state quiet icon="translate" :text="__('messages.no_translations')" />
            @endforelse
        </x-card>

        <div class="mt-4">{{ $translations->links() }}</div>
    </div>
@endsection
