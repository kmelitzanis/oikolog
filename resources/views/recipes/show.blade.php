@extends('layouts.app')
@section('title', $recipe->name)

@php
    $total = $recipe->totalMinutes();
    $steps = $recipe->steps();
    $ingredientsPayload = $recipe->ingredients->map(fn($i) => [
        'name' => $i->name, 'quantity' => (float) $i->quantity, 'unit' => $i->unit,
    ])->values();
@endphp

@section('content')
    <div class="max-w-5xl mx-auto"
         x-data="{
            servings: {{ max($recipe->servings, 1) }},
            baseServings: {{ max($recipe->servings, 1) }},
            base: {{ Illuminate\Support\Js::from($ingredientsPayload) }},
            listModal: false,
            listMode: '{{ $shoppingLists->isNotEmpty() ? 'existing' : 'new' }}',
            listId: '{{ $shoppingLists->first()->id ?? '' }}',
            newListName: '{{ $recipe->name }}',
            working: false,
            toast: null,
            scaled(q) { const v = q * this.servings / this.baseServings; return Math.round(v * 100) / 100; },
            async sendToList() {
                this.working = true;
                const body = { servings: this.servings };
                if (this.listMode === 'existing') body.shopping_list_id = this.listId; else body.new_list_name = this.newListName;
                try {
                    const res = await fetch('{{ route('recipes.to-shopping-list', $recipe) }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: JSON.stringify(body),
                    });
                    const data = await res.json();
                    if (!res.ok) { this.toast = { msg: data.message || 'Failed', err: true }; this.working = false; return; }
                    this.listModal = false;
                    this.toast = { msg: data.msg || data.message, err: false };
                    setTimeout(() => { if (data.url) window.location = data.url; }, 900);
                } catch (e) { this.toast = { msg: e.message, err: true }; }
                this.working = false;
            }
         }">

        {{-- Back --}}
        <a href="{{ route('recipes.index') }}"
           class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 text-sm font-semibold mb-4 hover:gap-2 transition-all">
            <span class="material-icons-round text-lg">arrow_back</span>{{ __('messages.recipes') }}
        </a>

        {{-- Hero --}}
        <div class="relative overflow-hidden rounded-3xl bg-linear-to-br from-indigo-600 via-indigo-500 to-purple-600 p-6 sm:p-8 mb-6 shadow-lg">
            <div class="absolute -right-6 -top-8 text-[10rem] opacity-20 select-none leading-none">{{ $recipe->emoji ?: '🍽️' }}</div>
            <div class="relative">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-3 mb-2">
                            <span class="text-4xl">{{ $recipe->emoji ?: '🍽️' }}</span>
                            @if($recipe->difficulty)
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-white/20 text-white backdrop-blur-sm">
                                    {{ __('messages.' . $recipe->difficulty) }}
                                </span>
                            @endif
                            @if($recipe->is_favorite)
                                <span class="material-icons-round text-red-300 text-xl">favorite</span>
                            @endif
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-white">{{ $recipe->name }}</h1>
                        @if($recipe->description)
                            <p class="text-indigo-100 text-sm mt-2 max-w-xl">{{ $recipe->description }}</p>
                        @endif
                    </div>
                    <div class="flex gap-2 shrink-0">
                        <a href="{{ route('recipes.edit', $recipe) }}"
                           class="w-10 h-10 rounded-xl bg-white/15 hover:bg-white/25 text-white flex items-center justify-center backdrop-blur-sm transition" title="{{ __('messages.edit') }}">
                            <span class="material-icons-round text-lg">edit</span>
                        </a>
                        <form method="POST" action="{{ route('recipes.destroy', $recipe) }}"
                              onsubmit="return confirm('{{ __('messages.delete') }}?')">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    class="w-10 h-10 rounded-xl bg-white/15 hover:bg-red-500/70 text-white flex items-center justify-center backdrop-blur-sm transition" title="{{ __('messages.delete') }}">
                                <span class="material-icons-round text-lg">delete</span>
                            </button>
                        </form>
                    </div>
                </div>

                {{-- Stat pills --}}
                <div class="flex flex-wrap gap-2.5 mt-5">
                    @if($recipe->prep_minutes)
                        <div class="inline-flex items-center gap-1.5 bg-white/15 backdrop-blur-sm rounded-xl px-3 py-1.5 text-white text-sm">
                            <span class="material-icons-round text-base">timer</span>
                            <span class="font-semibold">{{ $recipe->prep_minutes }}'</span><span class="text-indigo-100">{{ __('messages.prep_time') }}</span>
                        </div>
                    @endif
                    @if($recipe->cook_minutes)
                        <div class="inline-flex items-center gap-1.5 bg-white/15 backdrop-blur-sm rounded-xl px-3 py-1.5 text-white text-sm">
                            <span class="material-icons-round text-base">local_fire_department</span>
                            <span class="font-semibold">{{ $recipe->cook_minutes }}'</span><span class="text-indigo-100">{{ __('messages.cook_time') }}</span>
                        </div>
                    @endif
                    @if($total)
                        <div class="inline-flex items-center gap-1.5 bg-white/15 backdrop-blur-sm rounded-xl px-3 py-1.5 text-white text-sm">
                            <span class="material-icons-round text-base">schedule</span>
                            <span class="font-semibold">{{ $total }} {{ __('messages.min_short') }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            {{-- Ingredients --}}
            <aside class="lg:col-span-1 lg:sticky lg:top-20 space-y-4">
                <div class="bg-white dark:bg-slate-800 rounded-3xl border border-gray-100 dark:border-slate-700 shadow-sm p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-gray-900 dark:text-white">{{ __('messages.ingredients') }}</h3>
                        {{-- Servings stepper --}}
                        <div class="flex items-center gap-1 bg-gray-100 dark:bg-slate-700 rounded-xl p-1">
                            <button @click="servings = Math.max(1, servings - 1)"
                                    class="w-7 h-7 rounded-lg bg-white dark:bg-slate-600 text-gray-600 dark:text-slate-200 flex items-center justify-center hover:text-indigo-600 transition shadow-sm">
                                <span class="material-icons-round text-base">remove</span>
                            </button>
                            <span class="w-9 text-center text-sm font-bold text-gray-900 dark:text-white" x-text="servings"></span>
                            <button @click="servings = Math.min(50, servings + 1)"
                                    class="w-7 h-7 rounded-lg bg-white dark:bg-slate-600 text-gray-600 dark:text-slate-200 flex items-center justify-center hover:text-indigo-600 transition shadow-sm">
                                <span class="material-icons-round text-base">add</span>
                            </button>
                        </div>
                    </div>

                    <ul class="space-y-1">
                        <template x-for="(ing, i) in base" :key="i">
                            <li class="flex items-center justify-between gap-3 py-2 border-b border-gray-50 dark:border-slate-700/60 last:border-0">
                                <span class="text-sm text-gray-700 dark:text-slate-200" x-text="ing.name"></span>
                                <span class="text-sm font-semibold text-gray-900 dark:text-white whitespace-nowrap shrink-0">
                                    <span x-text="scaled(ing.quantity)"></span> <span class="text-gray-400 dark:text-slate-500 font-normal" x-text="ing.unit"></span>
                                </span>
                            </li>
                        </template>
                        @if($recipe->ingredients->isEmpty())
                            <li class="text-sm text-gray-400 py-2">—</li>
                        @endif
                    </ul>

                    <button @click="listModal = true" type="button"
                            class="w-full mt-4 inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl py-2.5 text-sm transition shadow-sm">
                        <span class="material-icons-round text-lg">add_shopping_cart</span>{{ __('messages.add_to_list') }}
                    </button>
                </div>
            </aside>

            {{-- Instructions --}}
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-slate-800 rounded-3xl border border-gray-100 dark:border-slate-700 shadow-sm p-5 sm:p-6">
                    <h3 class="font-bold text-gray-900 dark:text-white flex items-center gap-2 mb-5">
                        <span class="material-icons-round text-indigo-500">menu_book</span>{{ __('messages.instructions') }}
                    </h3>
                    @if(count($steps))
                        <ol class="space-y-4">
                            @foreach($steps as $i => $step)
                                <li class="flex gap-4">
                                    <span class="w-8 h-8 shrink-0 rounded-full bg-linear-to-br from-indigo-500 to-purple-500 text-white text-sm font-bold flex items-center justify-center shadow-sm">{{ $i + 1 }}</span>
                                    <p class="text-sm text-gray-700 dark:text-slate-200 leading-relaxed pt-1">{{ preg_replace('/^\s*\d+[\.\)]\s*/', '', $step) }}</p>
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <div class="text-center py-10">
                            <span class="material-icons-round text-4xl text-gray-300 dark:text-slate-600">description</span>
                            <p class="text-sm text-gray-400 dark:text-slate-500 mt-2">{{ __('messages.no_instructions') }}</p>
                            <a href="{{ route('recipes.edit', $recipe) }}" class="text-indigo-600 dark:text-indigo-400 text-sm font-semibold mt-2 inline-block">{{ __('messages.edit_recipe') }}</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Send-to-list modal --}}
        <div x-show="listModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
             x-transition.opacity>
            <div class="absolute inset-0 bg-black/40" @click="listModal = false"></div>
            <div class="relative w-full max-w-sm bg-white dark:bg-slate-800 rounded-3xl border border-gray-100 dark:border-slate-700 shadow-2xl p-6"
                 x-transition:enter="transition duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">{{ __('messages.add_to_list') }}</h3>
                <p class="text-sm text-gray-400 dark:text-slate-500 mb-5"><span x-text="servings"></span> {{ __('messages.servings') }}</p>

                @if($shoppingLists->isNotEmpty())
                    <div class="flex gap-2 mb-4 bg-gray-100 dark:bg-slate-700 rounded-xl p-1">
                        <button type="button" @click="listMode = 'existing'" :class="listMode==='existing' ? 'bg-white dark:bg-slate-600 shadow-sm text-gray-900 dark:text-white' : 'text-gray-500 dark:text-slate-400'"
                                class="flex-1 rounded-lg py-1.5 text-xs font-semibold transition">{{ __('messages.existing_list') }}</button>
                        <button type="button" @click="listMode = 'new'" :class="listMode==='new' ? 'bg-white dark:bg-slate-600 shadow-sm text-gray-900 dark:text-white' : 'text-gray-500 dark:text-slate-400'"
                                class="flex-1 rounded-lg py-1.5 text-xs font-semibold transition">{{ __('messages.create_new_list') }}</button>
                    </div>
                    <select x-show="listMode==='existing'" x-model="listId"
                            class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-indigo-500 mb-1">
                        @foreach($shoppingLists as $l)
                            <option value="{{ $l->id }}">{{ $l->name }}</option>
                        @endforeach
                    </select>
                @endif
                <input x-show="listMode==='new'" type="text" x-model="newListName" placeholder="{{ __('messages.new_list_name') }}"
                       class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-indigo-500 mb-1">

                <div class="flex gap-3 mt-5">
                    <button @click="sendToList()" :disabled="working"
                            class="flex-1 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white font-semibold rounded-xl py-2.5 text-sm transition">
                        <span x-text="working ? '…' : '{{ __('messages.send_to_list') }}'"></span>
                    </button>
                    <button @click="listModal = false" class="px-5 bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-300 font-semibold rounded-xl py-2.5 text-sm">{{ __('messages.cancel') }}</button>
                </div>
            </div>
        </div>

        {{-- Toast --}}
        <div x-show="toast" x-cloak x-transition
             class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 px-5 py-3 rounded-2xl shadow-xl text-white text-sm font-medium"
             :class="toast?.err ? 'bg-red-500' : 'bg-gray-900 dark:bg-slate-700'"
             x-text="toast?.msg"></div>
    </div>
@endsection
