@extends('layouts.app')
@section('title', __('messages.meal_planner'))

@php
    use Carbon\Carbon;
    // The mockup marks each meal with a small coloured dot rather than an
    // emoji — an amber ramp running through the day.
    $mealTypes = [
        'breakfast' => ['label' => __('messages.breakfast'), 'icon' => '☕',   'dot' => '#fcd34d'],
        'lunch'     => ['label' => __('messages.lunch'),     'icon' => '🥗',  'dot' => '#f59e0b'],
        'dinner'    => ['label' => __('messages.dinner'),    'icon' => '🍽️', 'dot' => '#fbbf24'],
        'snack'     => ['label' => __('messages.snack'),     'icon' => '🍎',  'dot' => '#d97706'],
    ];
    $today = Carbon::today();
    $days = [];
    for ($i = 0; $i < 7; $i++) {
        $d = $weekStart->copy()->addDays($i);
        $days[] = [
            'date'    => $d->toDateString(),
            'weekday' => $d->isoFormat('ddd'),
            'label'   => $d->isoFormat('D MMM'),
            'isToday' => $d->isSameDay($today),
        ];
    }
    $weekEnd = $weekStart->copy()->addDays(6);

    $payload = [
        'weekStart' => $weekStart->toDateString(),
        'days'      => $days,
        'mealTypes' => collect($mealTypes)->map(fn($m, $k) => ['key' => $k, 'label' => $m['label'], 'icon' => $m['icon']])->values(),
        'recipes'   => $recipes->map(fn($r) => ['id' => $r->id, 'name' => $r->name, 'emoji' => $r->emoji, 'servings' => $r->servings])->values(),
        'lists'     => $shoppingLists->map(fn($l) => ['id' => $l->id, 'name' => $l->name])->values(),
        'plans'     => $plans->map(fn($p) => [
            'id' => $p->id, 'date' => $p->date->toDateString(), 'meal_type' => $p->meal_type,
            'recipe_id' => $p->recipe_id, 'title' => $p->title, 'servings' => $p->servings, 'notes' => $p->notes,
            'name' => $p->displayName(), 'emoji' => $p->recipe?->emoji,
            'recipe_url' => $p->recipe_id ? route('recipes.show', $p->recipe_id) : null,
        ])->values(),
        'routes'    => [
            'index'  => route('meal-plans.index'),
            'store'  => route('meal-plans.store'),
            'base'   => url('meal-planner'),
            'toList' => route('meal-plans.to-shopping-list'),
        ],
    ];
@endphp

@section('content')
    <div x-data="mealPlanner({{ Illuminate\Support\Js::from($payload) }})" class="max-w-7xl mx-auto">

        {{-- Header --}}
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between mb-6">
            <div>
                <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">{{ __('messages.meal_planner') }}</h1>
                <p class="text-sm text-gray-400 dark:text-slate-500 mt-0.5" x-text="`${plannedCount} · {{ $weekStart->isoFormat('D MMM') }} – {{ $weekEnd->isoFormat('D MMM') }}`"></p>
            </div>
            <div class="flex items-center gap-2">
                <div class="flex items-center bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl overflow-hidden">
                    <button @click="gotoWeek(-7)" class="w-10 h-10 flex items-center justify-center text-gray-500 hover:text-amber-700 hover:bg-gray-50 dark:hover:bg-slate-700 transition" title="{{ __('messages.prev_week') }}">
                        <span class="material-icons-round">chevron_left</span>
                    </button>
                    <button @click="gotoToday()" class="px-3 h-10 text-xs font-semibold text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 border-x border-gray-200 dark:border-slate-700 transition">{{ __('messages.today') }}</button>
                    <button @click="gotoWeek(7)" class="w-10 h-10 flex items-center justify-center text-gray-500 hover:text-amber-700 hover:bg-gray-50 dark:hover:bg-slate-700 transition" title="{{ __('messages.next_week') }}">
                        <span class="material-icons-round">chevron_right</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Mobile / tablet: the seven day cards, one meal group per card.
             Below lg this reads better as stacked days than a cramped grid. --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 lg:hidden">
            @foreach($days as $day)
                <div class="flex flex-col overflow-hidden rounded-[24px] border bg-white dark:bg-slate-800
                            {{ $day['isToday'] ? 'border-amber-400 dark:border-amber-500' : 'border-gray-100 dark:border-slate-700' }}">
                    {{-- Day header --}}
                    <div class="p-3 text-center {{ $day['isToday'] ? 'bg-amber-500' : 'bg-gray-50 dark:bg-slate-900/50' }}">
                        <div class="text-[0.6rem] font-bold uppercase tracking-[0.08em]
                                    {{ $day['isToday'] ? 'text-slate-900/70' : 'text-gray-400 dark:text-slate-500' }}">
                            {{ $day['weekday'] }}
                        </div>
                        <div class="text-[1.2rem] font-extrabold mt-0.5
                                    {{ $day['isToday'] ? 'text-slate-900' : 'text-gray-900 dark:text-white' }}">
                            {{ \Carbon\Carbon::parse($day['date'])->day }}
                        </div>
                    </div>

                    {{-- Meal slots --}}
                    <div class="flex-1 p-3 space-y-2.5">
                        @foreach($mealTypes as $key => $meal)
                            <div>
                                <div class="flex items-center gap-1.5 mb-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full shrink-0" style="background: {{ $meal['dot'] }}"></span>
                                    <span class="text-[0.58rem] font-bold uppercase tracking-[0.07em] text-gray-400 dark:text-slate-500">{{ $meal['label'] }}</span>
                                </div>

                                <template x-for="plan in planFor('{{ $day['date'] }}', '{{ $key }}')" :key="plan.id">
                                    <div @click="openEdit(plan)"
                                         class="text-[0.8rem] leading-[1.35] font-semibold text-gray-900 dark:text-white mb-1.5 cursor-pointer hover:text-amber-600 dark:hover:text-amber-400 transition">
                                        <span x-show="plan.emoji" x-text="plan.emoji"></span><span x-text="plan.name"></span>
                                    </div>
                                </template>

                                <button @click="openAdd('{{ $day['date'] }}', '{{ $key }}')" type="button"
                                        :title="'{{ __('messages.add_meal') }}'"
                                        class="w-full flex items-center justify-center gap-1 border border-dashed border-gray-200 dark:border-slate-700 rounded-lg py-1 text-[11px] font-medium text-gray-300 dark:text-slate-600 hover:border-amber-400 hover:text-amber-500 hover:bg-amber-50/40 dark:hover:bg-amber-500/10 transition">
                                    <span class="material-icons-round text-sm">add</span>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Desktop: a calendar grid — meal types as rows, days as columns —
             rather than seven repeated cards, so the week reads at a glance
             the way the Calendar page does. --}}
        <div class="hidden lg:block rounded-[24px] border border-gray-100 dark:border-slate-700 bg-white dark:bg-slate-800 overflow-hidden">
            <div class="grid" style="grid-template-columns: 120px repeat(7, 1fr);">

                {{-- Day header row --}}
                <div class="border-b border-r border-gray-100 dark:border-slate-700"></div>
                @foreach($days as $day)
                    <div class="p-3 text-center border-b border-gray-100 dark:border-slate-700
                                {{ !$loop->last ? 'border-r' : '' }}
                                {{ $day['isToday'] ? 'bg-amber-500' : 'bg-gray-50 dark:bg-slate-900/50' }}">
                        <div class="text-[0.6rem] font-bold uppercase tracking-[0.08em]
                                    {{ $day['isToday'] ? 'text-slate-900/70' : 'text-gray-400 dark:text-slate-500' }}">
                            {{ $day['weekday'] }}
                        </div>
                        <div class="text-[1.1rem] font-extrabold mt-0.5
                                    {{ $day['isToday'] ? 'text-slate-900' : 'text-gray-900 dark:text-white' }}">
                            {{ \Carbon\Carbon::parse($day['date'])->day }}
                        </div>
                    </div>
                @endforeach

                {{-- One row per meal type --}}
                @foreach($mealTypes as $key => $meal)
                    <div class="flex items-center gap-1.5 px-3 py-3 border-r border-gray-100 dark:border-slate-700
                                {{ !$loop->last ? 'border-b' : '' }} bg-gray-50/60 dark:bg-slate-900/30">
                        <span class="w-1.5 h-1.5 rounded-full shrink-0" style="background: {{ $meal['dot'] }}"></span>
                        <span class="text-[0.62rem] font-bold uppercase tracking-[0.07em] text-gray-400 dark:text-slate-500">{{ $meal['label'] }}</span>
                    </div>
                    @foreach($days as $day)
                        <div class="group p-2 min-h-[76px] {{ !$loop->last ? 'border-r' : '' }} {{ !$loop->parent->last ? 'border-b' : '' }} border-gray-100 dark:border-slate-700
                                    {{ $day['isToday'] ? 'bg-amber-50/40 dark:bg-amber-500/[0.06]' : '' }}">
                            <template x-for="plan in planFor('{{ $day['date'] }}', '{{ $key }}')" :key="plan.id">
                                <div @click="openEdit(plan)"
                                     class="text-[0.78rem] leading-[1.35] font-semibold text-gray-900 dark:text-white mb-1 cursor-pointer hover:text-amber-600 dark:hover:text-amber-400 transition">
                                    <span x-show="plan.emoji" x-text="plan.emoji"></span><span x-text="plan.name"></span>
                                </div>
                            </template>
                            <button @click="openAdd('{{ $day['date'] }}', '{{ $key }}')" type="button"
                                    :title="'{{ __('messages.add_meal') }}'"
                                    class="w-full flex items-center justify-center gap-1 rounded-lg py-1 text-[11px] font-medium text-transparent group-hover:text-gray-300 dark:group-hover:text-slate-600 hover:!text-amber-500 hover:bg-amber-50/40 dark:hover:bg-amber-500/10 transition">
                                <span class="material-icons-round text-sm">add</span>
                            </button>
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>

        {{-- The mockup's week action sits below the grid as an outline button
             with amber text, rather than beside the title. --}}
        <button @click="openBuildList()" type="button"
                class="mt-4 h-11 px-[18px] rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-amber-600 dark:text-amber-400 text-[0.84rem] font-semibold whitespace-nowrap flex items-center gap-2.5 transition hover:bg-gray-50 dark:hover:bg-slate-700">
            <span class="material-icons-round text-base">shopping_cart</span>
            {{ __('messages.send_week_to_list') }}
        </button>

        {{-- ── Meal modal ─────────────────────────────────────────────────── --}}
        <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4" x-transition.opacity>
            <div class="absolute inset-0 bg-black/40" @click="modalOpen = false"></div>
            <div class="relative w-full sm:max-w-md bg-white dark:bg-slate-800 rounded-t-3xl sm:rounded-3xl border border-gray-100 dark:border-slate-700 shadow-2xl max-h-[90vh] flex flex-col"
                 x-transition:enter="transition duration-200" x-transition:enter-start="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white" x-text="editing ? '{{ __('messages.edit') }}' : '{{ __('messages.plan_meal') }}'"></h3>
                    <button @click="modalOpen = false" class="text-gray-400 hover:text-gray-700 dark:hover:text-white">
                        <span class="material-icons-round">close</span>
                    </button>
                </div>

                <div class="p-5 space-y-4 overflow-y-auto">
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
                                    <span class="text-lg" x-text="r.emoji || '🍽️'"></span>
                                    <span class="flex-1 text-sm font-medium text-gray-800 dark:text-slate-100 truncate" x-text="r.name"></span>
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
                    <button @click="saveMeal()" :disabled="working"
                            class="flex-1 bg-amber-500 hover:bg-amber-600 disabled:opacity-60 text-slate-900 font-semibold rounded-xl py-2.5 text-sm transition">
                        <span x-text="working ? '…' : '{{ __('messages.save') }}'"></span>
                    </button>
                    <button x-show="editing" @click="removeMeal(editing)" type="button"
                            class="px-4 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 font-semibold rounded-xl py-2.5 text-sm hover:bg-red-100 transition">
                        <span class="material-icons-round text-lg">delete</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Build list modal ───────────────────────────────────────────── --}}
        <div x-show="listModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" x-transition.opacity>
            <div class="absolute inset-0 bg-black/40" @click="listModalOpen = false"></div>
            <div class="relative w-full max-w-sm bg-white dark:bg-slate-800 rounded-3xl border border-gray-100 dark:border-slate-700 shadow-2xl p-6"
                 x-transition:enter="transition duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">{{ __('messages.build_shopping_list') }}</h3>
                <p class="text-sm text-gray-400 dark:text-slate-500 mb-5">{{ $weekStart->isoFormat('D MMM') }} – {{ $weekEnd->isoFormat('D MMM') }}</p>

                <div class="flex gap-2 mb-4 bg-gray-100 dark:bg-slate-700 rounded-xl p-1" x-show="lists.length">
                    <button type="button" @click="listForm.mode = 'existing'" :class="listForm.mode==='existing' ? 'bg-white dark:bg-slate-600 shadow-sm text-gray-900 dark:text-white' : 'text-gray-500 dark:text-slate-400'"
                            class="flex-1 rounded-lg py-1.5 text-xs font-semibold transition">{{ __('messages.existing_list') }}</button>
                    <button type="button" @click="listForm.mode = 'new'" :class="listForm.mode==='new' ? 'bg-white dark:bg-slate-600 shadow-sm text-gray-900 dark:text-white' : 'text-gray-500 dark:text-slate-400'"
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
                    <button @click="buildList()" :disabled="working"
                            class="flex-1 bg-amber-500 hover:bg-amber-600 disabled:opacity-60 text-slate-900 font-semibold rounded-xl py-2.5 text-sm transition">
                        <span x-text="working ? '…' : '{{ __('messages.build_shopping_list') }}'"></span>
                    </button>
                    <button @click="listModalOpen = false" class="px-5 bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-300 font-semibold rounded-xl py-2.5 text-sm">{{ __('messages.cancel') }}</button>
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
