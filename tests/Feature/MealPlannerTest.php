<?php

namespace Tests\Feature;

use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealPlannerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The week the planner shows is derived from the `week` query parameter.
     *
     * The client used to send a UTC-shifted date, which east of Greenwich landed
     * on the preceding Sunday; startOfWeek(MONDAY) then resolved that to the week
     * *before* the intended one, so "next week" returned the current week. The
     * client now sends a local civil date, and these pin the server half down.
     */
    public function test_a_monday_resolves_to_its_own_week(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('meal-plans.index', ['week' => '2026-08-03']));

        $response->assertOk();
        $this->assertSame('2026-08-03', $response->viewData('weekStart')->toDateString());
    }

    public function test_a_sunday_resolves_to_the_monday_that_starts_it(): void
    {
        $user = User::factory()->create();

        // Carbon treats Sunday as the last day of a Monday-based week, so this is
        // the previous Monday — exactly the off-by-one that broke navigation.
        $response = $this->actingAs($user)->get(route('meal-plans.index', ['week' => '2026-08-02']));

        $this->assertSame('2026-07-27', $response->viewData('weekStart')->toDateString());
    }

    public function test_the_payload_exposes_seven_days_and_the_plan_shape(): void
    {
        $user   = User::factory()->create();
        $recipe = Recipe::create([
            'user_id'      => $user->id,
            'name'         => 'Fasolada',
            'emoji'        => '🍲',
            'servings'     => 4,
            'prep_minutes' => 15,
            'cook_minutes' => 45,
        ]);

        MealPlan::create([
            'user_id'   => $user->id,
            'date'      => Carbon::today()->startOfWeek(Carbon::MONDAY)->toDateString(),
            'meal_type' => 'dinner',
            'recipe_id' => $recipe->id,
            'servings'  => 4,
        ]);

        $payload = $this->actingAs($user)->get(route('meal-plans.index'))->viewData('payload');

        $this->assertCount(7, $payload['days']);
        $this->assertCount(1, $payload['plans']);

        $plan = $payload['plans'][0];
        $this->assertSame('Fasolada', $plan['name']);
        // prep + cook were loaded and then dropped before reaching the grid.
        $this->assertSame(60, $plan['minutes']);
        $this->assertArrayHasKey('recipe_url', $plan);

        // Toast copy must come from the server, not be hardcoded in the JS.
        $this->assertNotEmpty($payload['i18n']['move_failed']);
    }

    public function test_dragging_a_meal_to_another_slot_updates_date_and_type(): void
    {
        $user = User::factory()->create();
        $monday = Carbon::today()->startOfWeek(Carbon::MONDAY);

        $plan = MealPlan::create([
            'user_id'   => $user->id,
            'date'      => $monday->toDateString(),
            'meal_type' => 'lunch',
            'title'     => 'Leftovers',
            'servings'  => 2,
        ]);

        $target = $monday->copy()->addDays(2)->toDateString();

        $response = $this->actingAs($user)->putJson(route('meal-plans.update', $plan), [
            'date'      => $target,
            'meal_type' => 'dinner',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.date', $target)
            ->assertJsonPath('data.meal_type', 'dinner');

        $plan->refresh();
        $this->assertSame($target, $plan->date->toDateString());
        $this->assertSame('dinner', $plan->meal_type);
    }

    public function test_a_meal_cannot_be_moved_by_another_user(): void
    {
        $owner     = User::factory()->create();
        $intruder  = User::factory()->create();

        $plan = MealPlan::create([
            'user_id'   => $owner->id,
            'date'      => Carbon::today()->toDateString(),
            'meal_type' => 'lunch',
            'title'     => 'Soup',
            'servings'  => 2,
        ]);

        $this->actingAs($intruder)
            ->putJson(route('meal-plans.update', $plan), ['meal_type' => 'dinner'])
            ->assertForbidden();

        $this->assertSame('lunch', $plan->fresh()->meal_type);
    }

    public function test_planner_page_renders(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('meal-plans.index'))
            ->assertOk();
    }
}
