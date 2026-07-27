@extends('layouts.app')
@section('title', $recipe ? __('messages.edit_recipe') : __('messages.create_recipe'))

@php
    $isEdit = (bool) $recipe;
    $initial = $recipe ? [
        'name' => $recipe->name,
        'emoji' => $recipe->emoji ?: '🍽️',
        'description' => $recipe->description,
        'servings' => $recipe->servings,
        'prep_minutes' => $recipe->prep_minutes,
        'cook_minutes' => $recipe->cook_minutes,
        'difficulty' => $recipe->difficulty ?: 'easy',
        'instructions' => $recipe->instructions,
        'ingredients' => $recipe->ingredients->map(fn($i) => [
            'name' => $i->name, 'quantity' => (float) $i->quantity, 'unit' => $i->unit, 'product_id' => $i->product_id,
        ])->values(),
    ] : [];
    $action = $isEdit ? route('recipes.update', $recipe) : route('recipes.store');
    $method = $isEdit ? 'PUT' : 'POST';
@endphp

@section('content')
    <div class="max-w-3xl mx-auto" x-data="recipeForm({{ Illuminate\Support\Js::from($initial) }})">
        {{-- Header --}}
        <div class="flex items-center gap-3 mb-6">
            <a href="{{ $isEdit ? route('recipes.show', $recipe) : route('recipes.index') }}"
               class="w-10 h-10 flex items-center justify-center rounded-xl bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-500 hover:text-gray-900 dark:hover:text-white transition">
                <span class="material-icons-round">arrow_back</span>
            </a>
            <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">
                {{ $isEdit ? __('messages.edit_recipe') : __('messages.create_recipe') }}
            </h1>
        </div>

        <div x-show="error" x-cloak
             class="mb-4 flex items-center gap-2 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 rounded-xl px-4 py-3 text-sm">
            <span class="material-icons-round text-lg">error_outline</span><span x-text="error"></span>
        </div>

        <form @submit.prevent="submit('{{ $action }}', '{{ $method }}')" class="space-y-5">
            {{-- Identity card --}}
            <x-card class="sm:p-6">
                <div class="flex gap-4">
                    {{-- Emoji picker --}}
                    <div x-data="{ open: false }" class="relative shrink-0">
                        <button type="button" @click="open = !open"
                                class="w-16 h-16 rounded-2xl bg-linear-to-br from-indigo-500 to-purple-500 flex items-center justify-center text-3xl shadow-sm hover:scale-105 transition">
                            <span x-text="form.emoji"></span>
                        </button>
                        <div x-show="open" x-cloak @click.outside="open = false"
                             x-transition
                             class="absolute z-20 mt-2 p-2 grid grid-cols-6 gap-1 bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-xl w-64">
                            <template x-for="e in emojiChoices" :key="e">
                                <button type="button" @click="form.emoji = e; open = false"
                                        class="w-9 h-9 rounded-lg text-xl hover:bg-gray-100 dark:hover:bg-slate-700 transition"
                                        x-text="e"></button>
                            </template>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1.5">{{ __('messages.recipe_name') }} *</label>
                        <input type="text" x-model="form.name" required
                               class="w-full bg-gray-50 dark:bg-slate-700/60 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm font-medium outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900/40 transition">
                        <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1.5 mt-3">{{ __('messages.description') }}</label>
                        <textarea x-model="form.description" rows="2"
                                  class="w-full bg-gray-50 dark:bg-slate-700/60 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-indigo-500 resize-none transition"></textarea>
                    </div>
                </div>

                {{-- Meta grid --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-5">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1.5">{{ __('messages.servings') }}</label>
                        <input type="number" min="1" max="50" x-model="form.servings"
                               class="w-full bg-gray-50 dark:bg-slate-700/60 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-3 py-2.5 text-sm outline-none focus:border-indigo-500 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1.5">{{ __('messages.prep_time') }} ({{ __('messages.min_short') }})</label>
                        <input type="number" min="0" x-model="form.prep_minutes"
                               class="w-full bg-gray-50 dark:bg-slate-700/60 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-3 py-2.5 text-sm outline-none focus:border-indigo-500 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1.5">{{ __('messages.cook_time') }} ({{ __('messages.min_short') }})</label>
                        <input type="number" min="0" x-model="form.cook_minutes"
                               class="w-full bg-gray-50 dark:bg-slate-700/60 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-3 py-2.5 text-sm outline-none focus:border-indigo-500 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1.5">{{ __('messages.difficulty') }}</label>
                        <select x-model="form.difficulty"
                                class="w-full bg-gray-50 dark:bg-slate-700/60 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-3 py-2.5 text-sm outline-none focus:border-indigo-500 transition">
                            <option value="easy">{{ __('messages.easy') }}</option>
                            <option value="medium">{{ __('messages.medium') }}</option>
                            <option value="hard">{{ __('messages.hard') }}</option>
                        </select>
                    </div>
                </div>
            </x-card>

            {{-- Ingredients card --}}
            <x-card class="sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="material-icons-round text-indigo-500 text-xl">restaurant</span>{{ __('messages.ingredients') }}
                    </h3>
                    <button type="button" @click="addIngredient()"
                            class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 text-sm font-semibold hover:text-indigo-700 transition">
                        <span class="material-icons-round text-lg">add</span>{{ __('messages.add_ingredient') }}
                    </button>
                </div>

                <div class="space-y-2">
                    <template x-for="(ing, idx) in form.ingredients" :key="idx">
                        <div class="flex items-center gap-2">
                            <span class="w-7 h-7 shrink-0 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 text-indigo-500 dark:text-indigo-400 text-xs font-bold flex items-center justify-center" x-text="idx + 1"></span>
                            <input type="text" x-model="ing.name" data-ing-name placeholder="{{ __('messages.ingredient') }}"
                                   class="flex-1 min-w-0 bg-gray-50 dark:bg-slate-700/60 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-3 py-2.5 text-sm outline-none focus:border-indigo-500 transition">
                            <input type="number" step="0.01" min="0" x-model="ing.quantity"
                                   class="w-16 shrink-0 bg-gray-50 dark:bg-slate-700/60 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-2 py-2.5 text-sm text-center outline-none focus:border-indigo-500 transition">
                            <input type="text" x-model="ing.unit" placeholder="{{ __('messages.unit') }}"
                                   class="w-20 shrink-0 bg-gray-50 dark:bg-slate-700/60 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-2 py-2.5 text-sm outline-none focus:border-indigo-500 transition">
                            <button type="button" @click="removeIngredient(idx)"
                                    class="w-9 h-9 shrink-0 rounded-xl text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center justify-center transition">
                                <span class="material-icons-round text-lg">close</span>
                            </button>
                        </div>
                    </template>
                </div>
            </x-card>

            {{-- Instructions card --}}
            <x-card class="sm:p-6">
                <h3 class="font-bold text-gray-900 dark:text-white flex items-center gap-2 mb-1">
                    <span class="material-icons-round text-indigo-500 text-xl">menu_book</span>{{ __('messages.instructions') }}
                </h3>
                <p class="text-xs text-gray-400 dark:text-slate-500 mb-3">{{ __('messages.instructions_hint') }}</p>
                <textarea x-model="form.instructions" rows="6"
                          class="w-full bg-gray-50 dark:bg-slate-700/60 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-3 text-sm outline-none focus:border-indigo-500 resize-y leading-relaxed transition"
                          placeholder="1. …&#10;2. …"></textarea>
            </x-card>

            {{-- Actions --}}
            <div class="flex gap-3 pb-2">
                <button type="submit" :disabled="submitting"
                        class="flex-1 inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white font-semibold rounded-xl py-3 text-sm shadow-sm transition">
                    <span class="material-icons-round text-lg" x-show="!submitting">check</span>
                    <span class="material-icons-round text-lg animate-spin" x-show="submitting" x-cloak>refresh</span>
                    <span x-text="submitting ? '…' : '{{ __('messages.save_recipe') }}'"></span>
                </button>
                <a href="{{ $isEdit ? route('recipes.show', $recipe) : route('recipes.index') }}"
                   class="px-6 inline-flex items-center bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 text-gray-700 dark:text-slate-300 font-semibold rounded-xl py-3 text-sm transition">
                    {{ __('messages.cancel') }}
                </a>
            </div>
        </form>
    </div>
@endsection
