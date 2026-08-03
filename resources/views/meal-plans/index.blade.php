@extends('layouts.app')
@section('title', __('messages.meal_planner'))

{{--
    Weekly meal planner.

    Desktop keeps the meal-types-as-rows / days-as-columns grid, but each planned
    meal is now a real card (servings, time, notes, recipe link) and every slot is
    a visible, drop-capable target — previously a planned meal was bare text and
    an empty week rendered 28 invisible cells.

    Moving a meal is drag & drop on pointer devices and the day / meal selects in
    the edit modal everywhere else; both go through the same PUT.
--}}

@section('content')
    <div x-data="mealPlanner({{ Illuminate\Support\Js::from($payload) }})"
         class="max-w-7xl mx-auto">

        {{-- ── Header ──────────────────────────────────────────────────── --}}
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between mb-5">
            <div class="min-w-0">
                <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">{{ __('messages.meal_planner') }}</h1>
                <p class="text-sm text-gray-400 dark:text-slate-500 mt-0.5">
                    {{ $weekStart->isoFormat('D MMM') }} – {{ $weekEnd->isoFormat('D MMM YYYY') }}
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <div class="flex items-center bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl overflow-hidden">
                    <button @click="gotoWeek(-7)" type="button" title="{{ __('messages.prev_week') }}"
                            class="w-10 h-10 flex items-center justify-center text-gray-500 hover:text-amber-700 hover:bg-gray-50 dark:hover:bg-slate-700 transition">
                        <span class="material-icons-round">chevron_left</span>
                    </button>
                    <button @click="gotoToday()" type="button"
                            class="px-3 h-10 text-xs font-semibold text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 border-x border-gray-200 dark:border-slate-700 transition">
                        {{ __('messages.today') }}
                    </button>
                    <button @click="gotoWeek(7)" type="button" title="{{ __('messages.next_week') }}"
                            class="w-10 h-10 flex items-center justify-center text-gray-500 hover:text-amber-700 hover:bg-gray-50 dark:hover:bg-slate-700 transition">
                        <span class="material-icons-round">chevron_right</span>
                    </button>
                </div>
                <x-btn variant="outline" type="button" icon="shopping_cart" @click="openBuildList()">
                    <span class="hidden sm:inline">{{ __('messages.send_week_to_list') }}</span>
                </x-btn>
            </div>
        </div>

        {{-- ── Week summary ────────────────────────────────────────────── --}}
        <div class="rounded-3xl border border-amber-500/25 bg-linear-to-br from-amber-500/[0.14] to-amber-500/[0.04] p-5 mb-5">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="min-w-0">
                    <div class="text-[0.66rem] font-semibold uppercase tracking-[0.09em] text-amber-700 dark:text-amber-400">
                        {{ __('messages.week_planned') }}
                    </div>
                    <div class="text-[2rem] leading-none font-extrabold tracking-[-0.02em] text-gray-900 dark:text-white mt-1.5">
                        <span x-text="filledSlots"></span><span class="text-gray-400 dark:text-slate-500 text-2xl">/<span x-text="totalSlots"></span></span>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-[0.66rem] font-semibold uppercase tracking-[0.09em] text-gray-500 dark:text-slate-400">
                        {{ __('messages.week_empty_slots') }}
                    </div>
                    <div class="text-lg font-bold text-gray-900 dark:text-white mt-1.5"
                         x-text="totalSlots - filledSlots"></div>
                </div>
            </div>

            <div class="h-2 rounded-full bg-gray-200/70 dark:bg-slate-900/50 mt-4 overflow-hidden">
                <div class="h-full rounded-full bg-amber-500 transition-[width] duration-[350ms] ease-out"
                     :style="`width: ${fillPercent}%`"></div>
            </div>

            <div class="flex flex-wrap gap-x-4 gap-y-1 text-[0.74rem] text-gray-600 dark:text-slate-400 mt-3">
                <span><span class="font-bold text-gray-900 dark:text-white" x-text="plannedCount"></span> {{ __('messages.meals') }}</span>
                <span><span class="font-bold text-gray-900 dark:text-white" x-text="recipeCount"></span> {{ __('messages.recipes') }}</span>
                <span><span class="font-bold text-gray-900 dark:text-white" x-text="servingsCount"></span> {{ __('messages.servings') }}</span>
            </div>
        </div>

        {{-- ── Reusable meal card ──────────────────────────────────────────
             One definition, rendered inside every slot on both layouts. --}}
        @php
            $mealCard = <<<'HTML'
                <div draggable="true"
                     @dragstart="onDragStart(plan, $event)"
                     @dragend="onDragEnd()"
                     @click="openEdit(plan)"
                     :class="dragging && dragging.id === plan.id ? 'opacity-40' : ''"
                     class="group/card w-full text-left bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700
                            rounded-xl px-2.5 py-2 mb-1.5 cursor-grab active:cursor-grabbing
                            hover:border-amber-300 dark:hover:border-amber-500/50 hover:shadow-sm transition">
                    <div class="flex items-start gap-1.5">
                        <template x-if="plan.image">
                            <img :src="plan.image" alt="" class="w-6 h-6 rounded-md object-cover shrink-0">
                        </template>
                        <span class="flex-1 min-w-0 text-[0.78rem] leading-[1.3] font-semibold text-gray-900 dark:text-white line-clamp-2"
                              x-text="plan.name"></span>
                    </div>
                    <div class="flex items-center gap-2 mt-1.5 text-[0.64rem] text-gray-400 dark:text-slate-500">
                        <span class="inline-flex items-center gap-0.5">
                            <span class="material-icons-round" style="font-size:11px;">group</span><span x-text="plan.servings"></span>
                        </span>
                        <template x-if="plan.minutes">
                            <span class="inline-flex items-center gap-0.5">
                                <span class="material-icons-round" style="font-size:11px;">schedule</span><span x-text="plan.minutes + '′'"></span>
                            </span>
                        </template>
                        <template x-if="plan.notes">
                            <span class="material-icons-round" style="font-size:11px;" :title="plan.notes">sticky_note_2</span>
                        </template>
                        <template x-if="plan.recipe_url">
                            <a :href="plan.recipe_url" @click.stop
                               class="ml-auto opacity-0 group-hover/card:opacity-100 text-amber-500 hover:text-amber-600 transition"
                               :title="plan.name">
                                <span class="material-icons-round" style="font-size:13px;">open_in_new</span>
                            </a>
                        </template>
                    </div>
                </div>
            HTML;
        @endphp

        {{-- ── Mobile / tablet: stacked day cards ──────────────────────── --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 lg:hidden">
            @foreach($days as $day)
                <x-card flush class="flex flex-col overflow-hidden
                        {{ $day['isToday'] ? 'border-amber-400 dark:border-amber-500' : '' }}">
                    <div class="p-3 text-center {{ $day['isToday'] ? 'bg-amber-500' : 'bg-gray-50 dark:bg-slate-900/50' }}">
                        <div class="text-[0.6rem] font-bold uppercase tracking-[0.08em]
                                    {{ $day['isToday'] ? 'text-slate-900/70' : 'text-gray-400 dark:text-slate-500' }}">
                            {{ $day['weekday'] }}
                        </div>
                        <div class="text-[1.2rem] font-extrabold mt-0.5
                                    {{ $day['isToday'] ? 'text-slate-900' : 'text-gray-900 dark:text-white' }}">
                            {{ $day['day'] }}
                        </div>
                    </div>

                    <div class="flex-1 p-3 space-y-3">
                        @foreach($mealTypes as $key => $meal)
                            <div @dragover.prevent="onDragOver('{{ $day['date'] }}', '{{ $key }}')"
                                 @dragleave="onDragLeave('{{ $day['date'] }}', '{{ $key }}')"
                                 @drop.prevent="onDrop('{{ $day['date'] }}', '{{ $key }}')"
                                 :class="dragOverSlot === slotKey('{{ $day['date'] }}', '{{ $key }}')
                                     ? 'ring-2 ring-amber-400 ring-offset-2 dark:ring-offset-slate-800 rounded-xl' : ''"
                                 class="transition">
                                <div class="flex items-center gap-1.5 mb-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full shrink-0" style="background: {{ $meal['dot'] }}"></span>
                                    <span class="text-[0.58rem] font-bold uppercase tracking-[0.07em] text-gray-400 dark:text-slate-500">{{ $meal['label'] }}</span>
                                </div>

                                <template x-for="plan in planFor('{{ $day['date'] }}', '{{ $key }}')" :key="plan.id">
                                    {!! $mealCard !!}
                                </template>

                                <button @click="openAdd('{{ $day['date'] }}', '{{ $key }}')" type="button"
                                        title="{{ __('messages.add_meal') }}"
                                        class="w-full flex items-center justify-center gap-1 border border-dashed border-gray-200 dark:border-slate-700 rounded-lg py-1.5 text-[11px] font-medium text-gray-300 dark:text-slate-600 hover:border-amber-400 hover:text-amber-500 hover:bg-amber-50/40 dark:hover:bg-amber-500/10 transition">
                                    <span class="material-icons-round text-sm">add</span>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endforeach
        </div>

        {{-- ── Desktop: the week grid ──────────────────────────────────── --}}
        <x-card flush class="hidden lg:block overflow-hidden">
            <div class="grid" style="grid-template-columns: 132px repeat(7, minmax(0, 1fr));">

                {{-- Day header row --}}
                <div class="border-b border-r border-gray-100 dark:border-slate-700"></div>
                @foreach($days as $day)
                    <div class="p-3 text-center border-b border-gray-100 dark:border-slate-700
                                {{ !$loop->last ? 'border-r' : '' }}
                                {{ $day['isToday'] ? 'bg-amber-500' : ($day['isPast'] ? 'bg-gray-50/60 dark:bg-slate-900/60' : 'bg-gray-50 dark:bg-slate-900/50') }}">
                        <div class="text-[0.6rem] font-bold uppercase tracking-[0.08em]
                                    {{ $day['isToday'] ? 'text-slate-900/70' : 'text-gray-400 dark:text-slate-500' }}">
                            {{ $day['weekday'] }}
                        </div>
                        <div class="text-[1.1rem] font-extrabold mt-0.5
                                    {{ $day['isToday'] ? 'text-slate-900' : ($day['isPast'] ? 'text-gray-400 dark:text-slate-600' : 'text-gray-900 dark:text-white') }}">
                            {{ $day['day'] }}
                        </div>
                    </div>
                @endforeach

                {{-- One row per meal type --}}
                @foreach($mealTypes as $key => $meal)
                    <div class="flex items-center gap-2 px-3 py-3 border-r border-gray-100 dark:border-slate-700
                                {{ !$loop->last ? 'border-b' : '' }} bg-gray-50/60 dark:bg-slate-900/30">
                        <span class="w-1.5 h-1.5 rounded-full shrink-0" style="background: {{ $meal['dot'] }}"></span>
                        <span class="text-[0.62rem] font-bold uppercase tracking-[0.07em] text-gray-400 dark:text-slate-500">{{ $meal['label'] }}</span>
                    </div>
                    @foreach($days as $day)
                        <div @dragover.prevent="onDragOver('{{ $day['date'] }}', '{{ $key }}')"
                             @dragleave="onDragLeave('{{ $day['date'] }}', '{{ $key }}')"
                             @drop.prevent="onDrop('{{ $day['date'] }}', '{{ $key }}')"
                             :class="{
                                 'ring-2 ring-inset ring-amber-400 bg-amber-50/60 dark:bg-amber-500/10': dragOverSlot === slotKey('{{ $day['date'] }}', '{{ $key }}'),
                                 'bg-amber-50/20 dark:bg-amber-500/[0.03]': dragging && isDropTarget('{{ $day['date'] }}', '{{ $key }}') && dragOverSlot !== slotKey('{{ $day['date'] }}', '{{ $key }}')
                             }"
                             class="group p-1.5 min-h-[92px] transition-colors
                                    {{ !$loop->last ? 'border-r' : '' }} {{ !$loop->parent->last ? 'border-b' : '' }} border-gray-100 dark:border-slate-700
                                    {{ $day['isToday'] ? 'bg-amber-50/40 dark:bg-amber-500/[0.06]' : '' }}">

                            <template x-for="plan in planFor('{{ $day['date'] }}', '{{ $key }}')" :key="plan.id">
                                {!! $mealCard !!}
                            </template>

                            {{-- Always visible, just quiet until hover. It used to be
                                 `text-transparent`, which made an empty week look
                                 like a dead page with nothing to click. --}}
                            <button @click="openAdd('{{ $day['date'] }}', '{{ $key }}')" type="button"
                                    title="{{ __('messages.add_meal') }}"
                                    class="w-full flex items-center justify-center rounded-lg py-1.5
                                           border border-dashed border-gray-200/80 dark:border-slate-700
                                           text-gray-300 dark:text-slate-600
                                           hover:border-amber-400 hover:text-amber-500 hover:bg-amber-50/40 dark:hover:bg-amber-500/10 transition">
                                <span class="material-icons-round text-sm">add</span>
                            </button>
                        </div>
                    @endforeach
                @endforeach
            </div>
        </x-card>

        {{-- Empty week nudge — the grid alone gives no hint that a week is blank. --}}
        <template x-if="plannedCount === 0">
            <div class="mt-4">
                <x-empty-state quiet icon="restaurant_menu"
                               :title="__('messages.meal_empty_week')"
                               :text="__('messages.meal_empty_week_hint')" />
            </div>
        </template>

        {{-- ── Meal modal ─────────────────────────────────────────────── --}}
        <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4" x-transition.opacity>
            <div class="absolute inset-0 bg-black/40" @click="modalOpen = false"></div>
            <div class="relative w-full sm:max-w-md bg-white dark:bg-slate-800 rounded-t-3xl sm:rounded-3xl border border-gray-100 dark:border-slate-700 shadow-2xl max-h-[90vh] flex flex-col"
                 x-transition:enter="transition duration-200" x-transition:enter-start="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white"
                        x-text="editing ? '{{ __('messages.edit') }}' : '{{ __('messages.plan_meal') }}'"></h3>
                    <button @click="modalOpen = false" type="button" class="text-gray-400 hover:text-gray-700 dark:hover:text-white">
                        <span class="material-icons-round">close</span>
                    </button>
                </div>

                <div class="p-5 space-y-4 overflow-y-auto">
                    {{-- Day / meal slot. This is also the accessible and
                         touch-friendly way to move a meal — drag & drop is a
                         pointer-only shortcut for the same update. --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1.5">{{ __('messages.day') }}</label>
                            <select x-model="form.date"
                                    class="w-full bg-gray-50 dark:bg-slate-700/60 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-3 py-2.5 text-sm outline-none focus:border-amber-500">
                                <template x-for="d in days" :key="d.date">
                                    <option :value="d.date" x-text="`${d.weekday} ${d.label}`"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1.5">{{ __('messages.meal') }}</label>
                            <select x-model="form.meal_type"
                                    class="w-full bg-gray-50 dark:bg-slate-700/60 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-3 py-2.5 text-sm outline-none focus:border-amber-500">
                                <template x-for="m in mealTypes" :key="m.key">
                                    <option :value="m.key" x-text="m.label"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    {{-- Recipe picker --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-2">{{ __('messages.choose_recipe') }}</label>
                        <div class="relative mb-2" x-show="recipes.length > 4">
                            <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg">search</span>
                            <input type="text" x-model="recipeSearch" placeholder="{{ __('messages.search') }}…"
                                   class="w-full bg-gray-50 dark:bg-slate-700/60 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl pl-10 pr-3 py-2 text-sm outline-none focus:border-amber-500">
                        </div>
                        <div class="max-h-44 overflow-y-auto space-y-1 -mx-1 px-1">
                            <template x-if="recipes.length === 0">
                                <a href="{{ route('recipes.create') }}" class="block text-center py-4 text-sm text-amber-700 dark:text-amber-400 font-semibold">+ {{ __('messages.create_recipe') }}</a>
                            </template>
                            <template x-for="r in filteredRecipes" :key="r.id">
                                <button type="button" @click="pickRecipe(r)"
                                        :class="form.recipe_id === r.id ? 'border-amber-400 bg-amber-50 dark:bg-amber-500/15' : 'border-gray-100 dark:border-slate-700 hover:border-gray-200 dark:hover:border-slate-600'"
                                        class="w-full flex items-center gap-3 border rounded-xl px-3 py-2 text-left transition">
                                    <template x-if="r.image">
                                        <img :src="r.image" alt="" class="w-9 h-9 rounded-lg object-cover shrink-0">
                                    </template>
                                    <template x-if="!r.image">
                                        <span class="w-9 h-9 rounded-lg bg-amber-50 dark:bg-amber-500/15 flex items-center justify-center shrink-0">
                                            <span class="material-icons-round text-amber-500 text-lg">restaurant_menu</span>
                                        </span>
                                    </template>
                                    <span class="flex-1 min-w-0">
                                        <span class="block text-sm font-medium text-gray-800 dark:text-slate-100 truncate" x-text="r.name"></span>
                                        <span class="block text-[0.68rem] text-gray-400 dark:text-slate-500"
                                              x-text="[r.servings ? `${r.servings} {{ __('messages.servings') }}` : null, r.minutes ? `${r.minutes}′` : null].filter(Boolean).join(' · ')"></span>
                                    </span>
                                    <span class="material-icons-round text-amber-500 text-lg" x-show="form.recipe_id === r.id">check_circle</span>
                                </button>
                            </template>
                        </div>
                    </div>

                    {{-- Custom meal --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1.5">{{ __('messages.or_custom_meal') }}</label>
                        <input type="text" x-model="form.title" @input="if(form.title) form.recipe_id = null"
                               placeholder="{{ __('messages.custom_meal') }}"
                               class="w-full bg-gray-50 dark:bg-slate-700/60 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-amber-500">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1.5">{{ __('messages.servings') }}</label>
                            <input type="number" min="1" max="50" x-model="form.servings"
                                   class="w-full bg-gray-50 dark:bg-slate-700/60 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-amber-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1.5">{{ __('messages.meal_notes') }}</label>
                            <input type="text" x-model="form.notes"
                                   class="w-full bg-gray-50 dark:bg-slate-700/60 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-amber-500">
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 px-5 py-4 border-t border-gray-100 dark:border-slate-700">
                    <x-btn type="button" class="flex-1" @click="saveMeal()" ::disabled="working">
                        <span x-text="working ? '…' : '{{ __('messages.save') }}'"></span>
                    </x-btn>
                    <x-btn variant="danger" type="button" icon="delete" x-show="editing"
                           :title="__('messages.delete')"
                           @click="removeMeal(editing)" />
                </div>
            </div>
        </div>

        {{-- ── Build list modal ───────────────────────────────────────── --}}
        <div x-show="listModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" x-transition.opacity>
            <div class="absolute inset-0 bg-black/40" @click="listModalOpen = false"></div>
            <div class="relative w-full max-w-sm bg-white dark:bg-slate-800 rounded-3xl border border-gray-100 dark:border-slate-700 shadow-2xl p-6"
                 x-transition:enter="transition duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">{{ __('messages.build_shopping_list') }}</h3>
                <p class="text-sm text-gray-400 dark:text-slate-500 mb-5">{{ $weekStart->isoFormat('D MMM') }} – {{ $weekEnd->isoFormat('D MMM') }}</p>

                <div class="flex gap-2 mb-4 bg-gray-100 dark:bg-slate-700 rounded-xl p-1" x-show="lists.length">
                    <button type="button" @click="listForm.mode = 'existing'"
                            :class="listForm.mode==='existing' ? 'bg-white dark:bg-slate-600 shadow-sm text-gray-900 dark:text-white' : 'text-gray-500 dark:text-slate-400'"
                            class="flex-1 rounded-lg py-1.5 text-xs font-semibold transition">{{ __('messages.existing_list') }}</button>
                    <button type="button" @click="listForm.mode = 'new'"
                            :class="listForm.mode==='new' ? 'bg-white dark:bg-slate-600 shadow-sm text-gray-900 dark:text-white' : 'text-gray-500 dark:text-slate-400'"
                            class="flex-1 rounded-lg py-1.5 text-xs font-semibold transition">{{ __('messages.create_new_list') }}</button>
                </div>

                <select x-show="listForm.mode==='existing' && lists.length" x-model="listForm.shopping_list_id"
                        class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-amber-500">
                    <template x-for="l in lists" :key="l.id">
                        <option :value="l.id" x-text="l.name"></option>
                    </template>
                </select>
                <input x-show="listForm.mode==='new' || !lists.length" type="text" x-model="listForm.new_list_name"
                       placeholder="{{ __('messages.new_list_name') }}"
                       class="w-full bg-gray-50 dark:bg-slate-700 dark:text-white border border-gray-200 dark:border-slate-600 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-amber-500">

                <div class="flex gap-3 mt-5">
                    <x-btn type="button" class="flex-1" @click="buildList()" ::disabled="working">
                        <span x-text="working ? '…' : '{{ __('messages.build_shopping_list') }}'"></span>
                    </x-btn>
                    <x-btn variant="ghost" type="button" @click="listModalOpen = false">{{ __('messages.cancel') }}</x-btn>
                </div>
            </div>
        </div>

        {{-- Toast --}}
        <div x-show="toast" x-cloak x-transition
             class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 px-5 py-3 rounded-2xl shadow-xl text-white text-sm font-medium"
             :class="toast?.isError ? 'bg-red-500' : 'bg-gray-900 dark:bg-slate-700'"
             x-text="toast?.msg"></div>
    </div>
@endsection
