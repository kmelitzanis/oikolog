<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Family;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Ids and amounts that arrive from a form are claims, not facts: each one has
 * to be checked against what the signed-in user is actually allowed to touch.
 */
class InputValidationTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attrs = []): User
    {
        return User::factory()->create(array_merge(['currency_code' => 'EUR', 'locale' => 'en'], $attrs));
    }

    private function bill(User $user, array $attrs = []): Bill
    {
        return Bill::create(array_merge([
            'name'          => 'Water',
            'category_id'   => Category::firstOrCreate(['name' => 'Utilities'])->id,
            'created_by'    => $user->id,
            'amount'        => 40,
            'currency_code' => 'EUR',
            'frequency'     => 'monthly',
            'start_date'    => now()->subMonth()->toDateString(),
            'next_due_date' => now()->addDays(2)->toDateString(),
            'is_active'     => true,
        ], $attrs));
    }

    private function account(User $user, string $name = 'Main'): Account
    {
        return Account::create(['name' => $name, 'created_by' => $user->id, 'currency_code' => 'EUR']);
    }

    public function test_a_payment_cannot_come_out_of_someone_elses_account(): void
    {
        $user = $this->user();
        $this->account($user);
        $foreign = $this->account($this->user(), 'Not yours');
        $bill = $this->bill($user);

        $this->actingAs($user)->post(route('bills.pay', $bill), ['account_id' => $foreign->id])
            ->assertSessionHasErrors('account_id');

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('account_transactions', 0);
    }

    public function test_a_payment_cannot_be_credited_to_a_stranger(): void
    {
        $user = $this->user();
        $stranger = $this->user();
        $bill = $this->bill($user);

        $this->actingAs($user)->post(route('bills.pay', $bill), ['paid_by_user_id' => $stranger->id])
            ->assertSessionHasErrors('paid_by_user_id');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_a_family_member_can_be_recorded_as_the_payer(): void
    {
        $user = $this->user();
        $family = Family::create(['name' => 'Home', 'owner_id' => $user->id]);
        $user->update(['family_id' => $family->id, 'family_role' => 'owner']);
        $partner = $this->user(['family_id' => $family->id, 'family_role' => 'member']);
        $bill = $this->bill($user);

        $this->actingAs($user)->post(route('bills.pay', $bill), ['paid_by_user_id' => $partner->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('payments', ['bill_id' => $bill->id, 'paid_by' => $partner->id]);
    }

    public function test_a_negative_partial_payment_is_refused(): void
    {
        $user = $this->user();
        $bill = $this->bill($user);

        $this->actingAs($user)->post(route('bills.pay', $bill), [
            'payment_mode' => 'partial', 'partial_amount' => '-500',
        ])->assertSessionHasErrors('partial_amount');

        $this->assertNull($bill->fresh()->remaining_balance);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_a_bill_link_must_be_a_web_address(): void
    {
        $user = $this->user();

        foreach (['javascript://alert(1)', 'data://text/html,hi', 'file:///etc/passwd'] as $url) {
            $this->actingAs($user)->post(route('bills.store'), [
                'name' => 'Phone', 'category_id' => Category::firstOrCreate(['name' => 'Utilities'])->id,
                'amount' => 20, 'frequency' => 'monthly', 'start_date' => now()->toDateString(),
                'url' => $url,
            ])->assertSessionHasErrors('url');
        }

        $this->assertDatabaseCount('bills', 0);
    }

    public function test_a_bill_cannot_default_to_a_foreign_account(): void
    {
        $user = $this->user();
        $foreign = $this->account($this->user(), 'Not yours');

        $this->actingAs($user)->post(route('bills.store'), [
            'name' => 'Phone', 'category_id' => Category::firstOrCreate(['name' => 'Utilities'])->id,
            'amount' => 20, 'frequency' => 'monthly', 'start_date' => now()->toDateString(),
            'default_account_id' => $foreign->id,
        ])->assertSessionHasErrors('default_account_id');
    }

    public function test_an_income_cannot_be_paid_into_a_foreign_account(): void
    {
        $user = $this->user();
        $foreign = $this->account($this->user(), 'Not yours');

        $this->actingAs($user)->post(route('income.store'), [
            'name' => 'Salary', 'amount' => 1000, 'frequency' => 'monthly',
            'start_date' => now()->toDateString(), 'account_id' => $foreign->id,
        ])->assertSessionHasErrors('account_id');

        $this->assertDatabaseCount('incomes', 0);
    }

    public function test_settings_reject_foreign_accounts_and_unknown_locales(): void
    {
        $user = $this->user();
        $foreign = $this->account($this->user(), 'Not yours');
        $base = ['name' => $user->name, 'email' => $user->email, 'currency_code' => 'EUR'];

        $this->actingAs($user)->post(route('settings.update'), $base + ['default_account_id' => $foreign->id])
            ->assertSessionHasErrors('default_account_id');
        $this->actingAs($user)->post(route('settings.update'), $base + ['locale' => '../../etc'])
            ->assertSessionHasErrors('locale');
        $this->actingAs($user)->post(route('settings.update'), ['currency_code' => "E'X"] + $base)
            ->assertSessionHasErrors('currency_code');

        $this->assertNull($user->fresh()->default_account_id);
    }

    public function test_a_new_avatar_replaces_the_old_one(): void
    {
        Storage::fake('public');
        $user = $this->user();
        $base = ['name' => $user->name, 'email' => $user->email, 'currency_code' => 'EUR'];

        $this->actingAs($user)->post(route('settings.update'), $base + ['avatar' => UploadedFile::fake()->image('a.png', 400, 300)]);
        $first = $user->fresh()->avatar_url;
        $this->assertNotNull($first);

        $this->actingAs($user->fresh())->post(route('settings.update'), $base + ['avatar' => UploadedFile::fake()->image('b.jpg', 300, 300)]);
        $second = $user->fresh()->avatar_url;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', parse_url($second, PHP_URL_PATH)));
        Storage::disk('public')->assertMissing(str_replace('/storage/', '', parse_url($first, PHP_URL_PATH)));
        $this->assertStringEndsWith('.jpg', $second);
    }

    public function test_picking_a_language_in_settings_takes_effect(): void
    {
        $user = $this->user();

        $this->actingAs($user)->withSession(['locale' => 'en'])->post(route('settings.update'), [
            'name' => $user->name, 'email' => $user->email, 'currency_code' => 'EUR', 'locale' => 'el',
        ])->assertSessionHas('locale', 'el');
    }
}
