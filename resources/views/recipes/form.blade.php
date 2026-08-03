@extends('layouts.app')
@section('title', $recipe ? __('messages.edit_recipe') : __('messages.create_recipe'))

@php
    $isEdit = (bool) $recipe;
    $initial = $recipe ? [
        'name' => $recipe->name,
        'description' => $recipe->description,
        'image_path' => $recipe->image_path,
        'image_url' => $recipe->imageUrl(),
        'source_url' => $recipe->source_url,
        'servings' => $recipe->servings,
        'prep_minutes' => $recipe->prep_minutes,
        'cook_minutes' => $recipe->cook_minutes,
        'difficulty' => $recipe->difficulty ?: 'easy',
        'ingredients' => $recipe->ingredients->sortBy('sort_order')->map(fn($i) => [
            'section' => $i->section ?? '', 'name' => $i->name,
            'quantity' => (float) $i->quantity, 'unit' => $i->unit, 'product_id' => $i->product_id,
        ])->values(),
        'steps' => $recipe->steps->sortBy('sort_order')->map(fn($st) => [
            'section' => $st->section ?? '', 'text' => $st->text,
        ])->values(),
    ] : [];

    // Canonical unit keys with localised labels — the picker must never show a
    // raw key, and what's stored must never be a translated label.
    $unitOptions = \App\Support\Units::options();
    $action = $isEdit ? route('recipes.update', $recipe) : route('recipes.store');
    $method = $isEdit ? 'PUT' : 'POST';

    // The Alpine component renders user-facing text, so every string it can show
    // is handed to it from here rather than hardcoded in the JS.
    $config = [
        'routes' => [
            'upload' => route('recipes.image.upload'),
            'import' => route('recipes.import'),
        ],
        'i18n' => [
            'name_required'       => __('messages.recipe_name_required'),
            'ingredient_required' => __('messages.recipe_ingredient_required'),
            'save_failed'         => __('messages.recipe_save_failed'),
            'image_failed'        => __('messages.image_upload_failed'),
            'import_failed'       => __('messages.import_failed'),
            'import_review'       => __('messages.import_review'),
            'import_partial'      => __('messages.import_partial'),
            'section_default'     => __('messages.section_default'),
        ],
    ];
@endphp

@section('content')
    <div class="max-w-3xl mx-auto"
         x-data="recipeForm({{ Illuminate\Support\Js::from($initial) }}, {{ Illuminate\Support\Js::from($config) }})">
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

        {{-- ── Import from a URL ───────────────────────────────────────────
             Offered above the form because it is the fastest way to fill it —
             the user pastes a link, checks what came back, then saves. --}}
        <x-card class="mb-5">
            <button type="button" @click="importOpen = !importOpen"
                    class="w-full flex items-center gap-3 text-left">
                <div class="w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-500/15 flex items-center justify-center shrink-0">
                    <span class="material-icons-round text-amber-500 text-lg">link</span>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-bold text-gray-900 dark:text-white">{{ __('messages.import_from_url') }}</div>
                    <div class="text-xs text-gray-400 dark:text-slate-500 mt-0.5">{{ __('messages.import_from_url_hint') }}</div>
                </div>
                <span class="material-icons-round text-gray-400 transition"
                      :class="importOpen ? 'rotate-180' : ''">expand_more</span>
            </button>

            <div x-show="importOpen" x-collapse x-cloak>
                <div class="flex flex-col sm:flex-row gap-2 mt-4">
                    <input type="url" x-model="importUrl" @keydown.enter.prevent="runImport()"
                           placeholder="https://…"
                           class="flex-1 min-w-0 bg-gray-50 dark:bg-slate-700/60 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-amber-500 transition">
                    <x-btn type="button" @click="runImport()" ::disabled="importing || !importUrl.trim()" icon="download">
                        <span x-text="importing ? '…' : '{{ __('messages.import') }}'"></span>
                    </x-btn>
                </div>
            </div>
        </x-card>

        <div x-show="importNotice" x-cloak
             class="mb-4 flex items-start gap-2 bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 text-amber-800 dark:text-amber-300 rounded-xl px-4 py-3 text-sm">
            <span class="material-icons-round text-lg shrink-0">info</span><span x-text="importNotice"></span>
        </div>

        <form @submit.prevent="submit('{{ $action }}', '{{ $method }}')" class="space-y-5">
            {{-- Identity card --}}
            <x-card class="sm:p-6">
                <div class="flex flex-col sm:flex-row gap-4">
                    {{-- Photo --}}
                    <div class="shrink-0">
                        <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1.5 sm:sr-only">{{ __('messages.recipe_photo') }}</label>
                        <div class="relative w-full sm:w-32 h-32 rounded-2xl overflow-hidden border border-gray-200 dark:border-slate-600 bg-gray-50 dark:bg-slate-700/60 group">
                            <template x-if="imageUrl">
                                <img :src="imageUrl" alt="" class="w-full h-full object-cover">
                            </template>

                            <label class="absolute inset-0 flex flex-col items-center justify-center cursor-pointer transition"
                                   :class="imageUrl ? 'opacity-0 group-hover:opacity-100 bg-black/50 text-white' : 'text-gray-400 dark:text-slate-500 hover:text-amber-500'">
                                <span class="material-icons-round text-2xl" x-show="!uploading">photo_camera</span>
                                <span class="material-icons-round text-2xl animate-spin" x-show="uploading" x-cloak>refresh</span>
                                <span class="text-[0.62rem] font-semibold mt-1 px-2 text-center"
                                      x-text="imageUrl ? '{{ __('messages.change_photo') }}' : '{{ __('messages.add_photo') }}'"></span>
                                <input type="file" accept="image/jpeg,image/png,image/webp,image/gif"
                                       class="hidden" @change="pickImage($event)">
                            </label>

                            <button type="button" x-show="imageUrl" x-cloak @click="clearImage()"
                                    title="{{ __('messages.remove_photo') }}"
                                    class="absolute top-1.5 right-1.5 w-6 h-6 rounded-lg bg-black/60 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 hover:bg-red-500 transition">
                                <span class="material-icons-round" style="font-size:14px;">close</span>
                            </button>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1.5">{{ __('messages.recipe_name') }} *</label>
                        <input type="text" x-model="form.name" required
                               class="w-full bg-gray-50 dark:bg-slate-700/60 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm font-medium outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 dark:focus:ring-amber-500/30 transition">
                        <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1.5 mt-3">{{ __('messages.description') }}</label>
                        <textarea x-model="form.description" rows="2"
                                  class="w-full bg-gray-50 dark:bg-slate-700/60 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-amber-500 resize-none transition"></textarea>

                        {{-- Attribution for an imported recipe, and the user's way
                             to detach it if they've rewritten the thing. --}}
                        <div x-show="form.source_url" x-cloak class="flex items-center gap-1.5 mt-2 min-w-0">
                            <span class="material-icons-round text-gray-400 shrink-0" style="font-size:14px;">link</span>
                            <a :href="form.source_url" target="_blank" rel="noopener noreferrer"
                               class="text-xs text-amber-700 dark:text-amber-400 hover:underline truncate"
                               x-text="form.source_url"></a>
                            <button type="button" @click="form.source_url = null"
                                    title="{{ __('messages.remove_source') }}"
                                    class="text-gray-300 hover:text-red-500 transition shrink-0">
                                <span class="material-icons-round" style="font-size:14px;">close</span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Meta grid --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-5">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1.5">{{ __('messages.servings') }}</label>
                        <input type="number" min="1" max="50" x-model="form.servings"
                               class="w-full bg-gray-50 dark:bg-slate-700/60 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-3 py-2.5 text-sm outline-none focus:border-amber-500 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1.5">{{ __('messages.prep_time') }} ({{ __('messages.min_short') }})</label>
                        <input type="number" min="0" x-model="form.prep_minutes"
                               class="w-full bg-gray-50 dark:bg-slate-700/60 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-3 py-2.5 text-sm outline-none focus:border-amber-500 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1.5">{{ __('messages.cook_time') }} ({{ __('messages.min_short') }})</label>
                        <input type="number" min="0" x-model="form.cook_minutes"
                               class="w-full bg-gray-50 dark:bg-slate-700/60 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-3 py-2.5 text-sm outline-none focus:border-amber-500 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1.5">{{ __('messages.difficulty') }}</label>
                        <select x-model="form.difficulty"
                                class="w-full bg-gray-50 dark:bg-slate-700/60 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-3 py-2.5 text-sm outline-none focus:border-amber-500 transition">
                            <option value="easy">{{ __('messages.easy') }}</option>
                            <option value="medium">{{ __('messages.medium') }}</option>
                            <option value="hard">{{ __('messages.hard') }}</option>
                        </select>
                    </div>
                </div>
            </x-card>

            {{-- ── Ingredients ─────────────────────────────────────────────
                 Grouped by heading. A section is just the label stored on each
                 row, so there is no separate list of sections to fall out of
                 sync with the rows themselves. --}}
            <x-card class="sm:p-6">
                <div class="flex items-center justify-between mb-4 gap-3">
                    <h3 class="font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="material-icons-round text-amber-500 text-xl">restaurant</span>{{ __('messages.ingredients') }}
                    </h3>
                    <button type="button" @click="addIngredientSection()"
                            class="inline-flex items-center gap-1 text-gray-500 dark:text-slate-400 text-xs font-semibold hover:text-amber-600 transition">
                        <span class="material-icons-round text-base">segment</span>{{ __('messages.add_section') }}
                    </button>
                </div>

                <template x-for="(section, si) in ingredientSections" :key="'ing-' + si">
                    <div class="mb-4 last:mb-0">
                        {{-- Heading row, only once the recipe actually has parts --}}
                        <div x-show="ingredientSections.length > 1 || section !== ''"
                             class="flex items-center gap-2 mb-2">
                            <span class="material-icons-round text-gray-300 dark:text-slate-600 text-base shrink-0">subdirectory_arrow_right</span>
                            <input type="text" :value="section"
                                   @change="renameSection(form.ingredients, section, $event.target.value)"
                                   placeholder="{{ __('messages.section_placeholder') }}"
                                   class="flex-1 min-w-0 bg-transparent border-0 border-b border-dashed border-gray-200 dark:border-slate-600 px-0 py-1 text-sm font-semibold text-gray-700 dark:text-slate-200 outline-none focus:border-amber-500 transition">
                            <button type="button" @click="removeSection(form.ingredients, section)"
                                    title="{{ __('messages.remove_section') }}"
                                    class="w-7 h-7 shrink-0 rounded-lg text-gray-300 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center justify-center transition">
                                <span class="material-icons-round text-base">delete_outline</span>
                            </button>
                        </div>

                        <div class="space-y-2">
                            <template x-for="(ing, idx) in rowsIn(form.ingredients, section)" :key="'ing-' + si + '-' + idx">
                                <div class="flex items-center gap-2">
                                    <span class="w-7 h-7 shrink-0 rounded-lg bg-amber-50 dark:bg-amber-500/15 text-amber-500 dark:text-amber-400 text-xs font-bold flex items-center justify-center" x-text="idx + 1"></span>
                                    <input type="text" x-model="ing.name" data-ing-name placeholder="{{ __('messages.ingredient') }}"
                                           class="flex-1 min-w-0 bg-gray-50 dark:bg-slate-700/60 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-3 py-2.5 text-sm outline-none focus:border-amber-500 transition">
                                    <input type="number" step="0.01" min="0" x-model="ing.quantity"
                                           class="w-16 shrink-0 bg-gray-50 dark:bg-slate-700/60 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-2 py-2.5 text-sm text-center outline-none focus:border-amber-500 transition">
                                    {{-- A fixed vocabulary, shown translated: the option value is the
                                         canonical key, the label is what the user reads. --}}
                                    <select x-model="ing.unit"
                                            class="w-24 shrink-0 bg-gray-50 dark:bg-slate-700/60 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-2 py-2.5 text-sm outline-none focus:border-amber-500 transition">
                                        @foreach($unitOptions as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" @click="removeIngredient(indexOf(form.ingredients, ing))"
                                            class="w-9 h-9 shrink-0 rounded-xl text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center justify-center transition">
                                        <span class="material-icons-round text-lg">close</span>
                                    </button>
                                </div>
                            </template>
                        </div>

                        <button type="button" @click="addIngredient(section)"
                                class="inline-flex items-center gap-1 mt-2 text-amber-700 dark:text-amber-400 text-sm font-semibold hover:text-amber-600 transition">
                            <span class="material-icons-round text-lg">add</span>{{ __('messages.add_ingredient') }}
                        </button>
                    </div>
                </template>
            </x-card>

            {{-- ── Method ──────────────────────────────────────────────────
                 One row per step rather than a single textarea, so a step can
                 belong to a section and the order is explicit. --}}
            <x-card class="sm:p-6">
                <div class="flex items-center justify-between mb-1 gap-3">
                    <h3 class="font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="material-icons-round text-amber-500 text-xl">menu_book</span>{{ __('messages.instructions') }}
                    </h3>
                    <button type="button" @click="addStepSection()"
                            class="inline-flex items-center gap-1 text-gray-500 dark:text-slate-400 text-xs font-semibold hover:text-amber-600 transition">
                        <span class="material-icons-round text-base">segment</span>{{ __('messages.add_section') }}
                    </button>
                </div>
                <p class="text-xs text-gray-400 dark:text-slate-500 mb-4">{{ __('messages.instructions_hint') }}</p>

                <template x-for="(section, si) in stepSections" :key="'step-' + si">
                    <div class="mb-4 last:mb-0">
                        <div x-show="stepSections.length > 1 || section !== ''"
                             class="flex items-center gap-2 mb-2">
                            <span class="material-icons-round text-gray-300 dark:text-slate-600 text-base shrink-0">subdirectory_arrow_right</span>
                            <input type="text" :value="section"
                                   @change="renameSection(form.steps, section, $event.target.value)"
                                   placeholder="{{ __('messages.section_placeholder') }}"
                                   class="flex-1 min-w-0 bg-transparent border-0 border-b border-dashed border-gray-200 dark:border-slate-600 px-0 py-1 text-sm font-semibold text-gray-700 dark:text-slate-200 outline-none focus:border-amber-500 transition">
                            <button type="button" @click="removeSection(form.steps, section)"
                                    title="{{ __('messages.remove_section') }}"
                                    class="w-7 h-7 shrink-0 rounded-lg text-gray-300 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center justify-center transition">
                                <span class="material-icons-round text-base">delete_outline</span>
                            </button>
                        </div>

                        <div class="space-y-2">
                            <template x-for="(step, idx) in rowsIn(form.steps, section)" :key="'step-' + si + '-' + idx">
                                <div class="flex items-start gap-2">
                                    <span class="w-7 h-7 mt-1 shrink-0 rounded-full bg-linear-to-br from-amber-500 to-amber-400 text-slate-900 text-xs font-bold flex items-center justify-center" x-text="idx + 1"></span>
                                    <textarea x-model="step.text" rows="2"
                                              placeholder="{{ __('messages.step_placeholder') }}"
                                              class="flex-1 min-w-0 bg-gray-50 dark:bg-slate-700/60 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-3 py-2.5 text-sm outline-none focus:border-amber-500 resize-y leading-relaxed transition"></textarea>
                                    <button type="button" @click="removeStep(indexOf(form.steps, step))"
                                            class="w-9 h-9 mt-1 shrink-0 rounded-xl text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center justify-center transition">
                                        <span class="material-icons-round text-lg">close</span>
                                    </button>
                                </div>
                            </template>
                        </div>

                        <button type="button" @click="addStep(section)"
                                class="inline-flex items-center gap-1 mt-2 text-amber-700 dark:text-amber-400 text-sm font-semibold hover:text-amber-600 transition">
                            <span class="material-icons-round text-lg">add</span>{{ __('messages.add_step') }}
                        </button>
                    </div>
                </template>
            </x-card>

            {{-- Actions --}}
            <div class="flex gap-3 pb-2">
                <button type="submit" :disabled="submitting"
                        class="flex-1 inline-flex items-center justify-center gap-2 bg-amber-500 hover:bg-amber-600 disabled:opacity-60 text-slate-900 font-semibold rounded-xl py-3 text-sm shadow-sm transition">
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
