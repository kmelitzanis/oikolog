<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\ShoppingList;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MealPlanController extends Controller
{
    public function index(Request $request)
    {
        $weekStart = $this->weekStart($request->get('week'));
        $weekEnd   = $weekStart->copy()->addDays(6);

        $plans = MealPlan::with(['recipe.ingredients'])
            ->where('user_id', $request->user()->id)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->orderBy('date')
            ->get();

        $recipes = Recipe::where('user_id', $request->user()->id)
            ->withCount('ingredients')
            ->orderBy('name')
            ->get(['id', 'name', 'image_path', 'servings', 'prep_minutes', 'cook_minutes']);

        $shoppingLists = ShoppingList::where('user_id', $request->user()->id)
            ->where('is_completed', false)
            ->orderByDesc('created_at')
            ->get(['id', 'name']);

        $today = Carbon::today();
        $days  = collect(range(0, 6))->map(function ($i) use ($weekStart, $today) {
            $d = $weekStart->copy()->addDays($i);

            return [
                'date'    => $d->toDateString(),
                'weekday' => $d->isoFormat('ddd'),
                'day'     => $d->day,
                'label'   => $d->isoFormat('D MMM'),
                'isToday' => $d->isSameDay($today),
                'isPast'  => $d->lt($today),
            ];
        })->all();

        return view('meal-plans.index', [
            'weekStart' => $weekStart,
            'weekEnd'   => $weekEnd,
            'days'      => $days,
            'mealTypes' => self::mealTypeMeta(),
            // Everything Alpine needs, assembled here so the view does no
            // serialization and the grid's plan shape matches what a save returns.
            'payload'   => [
                'weekStart' => $weekStart->toDateString(),
                'days'      => $days,
                'mealTypes' => collect(self::mealTypeMeta())
                    ->map(fn ($m, $k) => ['key' => $k, 'label' => $m['label']])->values(),
                'recipes'   => $recipes->map(fn ($r) => [
                    'id'       => $r->id,
                    'name'     => $r->name,
                    'image'    => $r->imageUrl(),
                    'servings' => $r->servings,
                    'minutes'  => ((int) $r->prep_minutes + (int) $r->cook_minutes) ?: null,
                ])->values(),
                'lists'     => $shoppingLists->map(fn ($l) => ['id' => $l->id, 'name' => $l->name])->values(),
                'plans'     => $plans->map(fn ($p) => self::planResource($p))->values(),
                'routes'    => [
                    'index'  => route('meal-plans.index'),
                    'store'  => route('meal-plans.store'),
                    'base'   => url('meal-planner'),
                    'toList' => route('meal-plans.to-shopping-list'),
                ],
                // The Alpine component renders user-facing text, so it must not
                // hardcode any — it was shipping English toasts in a bilingual app.
                'i18n'      => [
                    'pick_recipe_or_title' => __('messages.meal_pick_recipe_or_title'),
                    'save_failed'          => __('messages.meal_save_failed'),
                    'delete_failed'        => __('messages.meal_delete_failed'),
                    'move_failed'          => __('messages.meal_move_failed'),
                ],
            ],
        ]);
    }

    /** Meal slots in the order they occur in a day, with the grid's colour ramp. */
    public static function mealTypeMeta(): array
    {
        return [
            'breakfast' => ['label' => __('messages.breakfast'), 'icon' => 'wb_twilight',   'dot' => '#fcd34d'],
            'lunch'     => ['label' => __('messages.lunch'),     'icon' => 'lunch_dining',  'dot' => '#f59e0b'],
            'dinner'    => ['label' => __('messages.dinner'),    'icon' => 'dinner_dining', 'dot' => '#fbbf24'],
            'snack'     => ['label' => __('messages.snack'),     'icon' => 'cookie',        'dot' => '#d97706'],
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date'      => ['required', 'date'],
            'meal_type' => ['required', 'in:' . implode(',', MealPlan::MEAL_TYPES)],
            'recipe_id' => ['nullable', 'exists:recipes,id'],
            'title'     => ['nullable', 'string', 'max:255', 'required_without:recipe_id'],
            'servings'  => ['nullable', 'integer', 'min:1', 'max:50'],
            'notes'     => ['nullable', 'string', 'max:500'],
        ]);

        if (!empty($data['recipe_id'])) {
            $recipe = Recipe::findOrFail($data['recipe_id']);
            abort_unless($recipe->user_id === $request->user()->id, 403);
        }

        $plan = MealPlan::create([
            ...$data,
            'servings' => $data['servings'] ?? 2,
            'user_id'  => $request->user()->id,
        ]);

        return response()->json(['data' => $this->planResource($plan->load('recipe'))], 201);
    }

    public function update(Request $request, MealPlan $mealPlan)
    {
        abort_unless($mealPlan->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'recipe_id' => ['nullable', 'exists:recipes,id'],
            'title'     => ['nullable', 'string', 'max:255'],
            'servings'  => ['nullable', 'integer', 'min:1', 'max:50'],
            'notes'     => ['nullable', 'string', 'max:500'],
            'date'      => ['sometimes', 'date'],
            'meal_type' => ['sometimes', 'in:' . implode(',', MealPlan::MEAL_TYPES)],
        ]);

        if (!empty($data['recipe_id'])) {
            $recipe = Recipe::findOrFail($data['recipe_id']);
            abort_unless($recipe->user_id === $request->user()->id, 403);
        }

        $mealPlan->update($data);

        return response()->json(['data' => $this->planResource($mealPlan->fresh('recipe'))]);
    }

    public function destroy(Request $request, MealPlan $mealPlan)
    {
        abort_unless($mealPlan->user_id === $request->user()->id, 403);
        $mealPlan->delete();

        return response()->json(['message' => 'deleted']);
    }

    /**
     * Aggregate the ingredients of every recipe-linked meal in the given week
     * (scaled by planned servings) into a shopping list — an existing one or a
     * freshly created one.
     */
    public function toShoppingList(Request $request)
    {
        $data = $request->validate([
            'week'             => ['required', 'date'],
            'shopping_list_id' => ['nullable', 'exists:shopping_lists,id'],
            'new_list_name'    => ['nullable', 'string', 'max:255', 'required_without:shopping_list_id'],
        ]);

        $weekStart = $this->weekStart($data['week']);
        $weekEnd   = $weekStart->copy()->addDays(6);

        $plans = MealPlan::with('recipe.ingredients')
            ->where('user_id', $request->user()->id)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->whereNotNull('recipe_id')
            ->get();

        if ($plans->isEmpty()) {
            return response()->json(['message' => __('messages.meal_no_recipes_in_week')], 422);
        }

        // Merge ingredients by name+unit, scaling quantity by servings ratio.
        $merged = [];
        foreach ($plans as $plan) {
            $recipe = $plan->recipe;
            if (! $recipe) continue;
            $ratio = $recipe->servings > 0 ? $plan->servings / $recipe->servings : 1;

            foreach ($recipe->ingredients as $ing) {
                $key = mb_strtolower(trim($ing->name)) . '|' . mb_strtolower(trim($ing->unit ?? ''));
                if (! isset($merged[$key])) {
                    $merged[$key] = ['name' => $ing->name, 'unit' => $ing->unit ?: 'piece', 'quantity' => 0.0];
                }
                $merged[$key]['quantity'] += (float) $ing->quantity * $ratio;
            }
        }

        $list = DB::transaction(function () use ($data, $request, $merged, $weekStart) {
            if (!empty($data['shopping_list_id'])) {
                $list = ShoppingList::findOrFail($data['shopping_list_id']);
                abort_unless($list->user_id === $request->user()->id, 403);
            } else {
                $list = ShoppingList::create([
                    'user_id' => $request->user()->id,
                    'name'    => $data['new_list_name'],
                    'description' => __('messages.meal_week_of', ['date' => $weekStart->format('d/m/Y')]),
                ]);
            }

            foreach ($merged as $row) {
                $list->items()->create([
                    'name'     => $row['name'],
                    'quantity' => round($row['quantity'], 2),
                    'unit'     => $row['unit'],
                ]);
            }

            return $list;
        });

        return response()->json([
            'message' => __('messages.meal_added_to_list', ['count' => count($merged)]),
            'list_id' => $list->id,
            'url'     => route('shopping-list.show', $list),
        ]);
    }

    private function weekStart(?string $date): Carbon
    {
        $d = $date ? Carbon::parse($date) : Carbon::today();
        return $d->startOfWeek(Carbon::MONDAY)->startOfDay();
    }

    /**
     * The shape the planner grid renders from. Kept in one place because the
     * index payload and the create/update JSON responses must agree — the grid
     * swaps a card for whatever a save returns.
     */
    public static function planResource(MealPlan $plan): array
    {
        $recipe = $plan->recipe;

        return [
            'id'        => $plan->id,
            'date'      => $plan->date->toDateString(),
            'meal_type' => $plan->meal_type,
            'recipe_id' => $plan->recipe_id,
            'title'     => $plan->title,
            'servings'  => $plan->servings,
            'notes'     => $plan->notes,
            'name'      => $plan->displayName(),
            'image'     => $recipe?->imageUrl(),
            // Total hands-on + cooking time, so a card can say how long the meal
            // takes. Both columns were already being loaded and then discarded.
            'minutes'   => $recipe ? ((int) $recipe->prep_minutes + (int) $recipe->cook_minutes) ?: null : null,
            'recipe_url' => $plan->recipe_id ? route('recipes.show', $plan->recipe_id) : null,
        ];
    }
}
