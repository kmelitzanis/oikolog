<?php

namespace Tests\Feature;

use App\Jobs\SendPaymentPushNotification;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Family;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductPurchase;
use App\Models\PushSubscription;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\SafeUrlFetcher;
use App\Services\WebPushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PrivacyBoundariesTest extends TestCase
{
    use RefreshDatabase;

    private function family(): array
    {
        $payer = User::factory()->create(['currency_code' => 'EUR']);
        $family = Family::create(['name' => 'Home', 'owner_id' => $payer->id]);
        $payer->update(['family_id' => $family->id, 'family_role' => 'owner']);
        $partner = User::factory()->create(['family_id' => $family->id, 'family_role' => 'member']);

        return [$payer->refresh(), $partner];
    }

    private function recordingSender(array &$sent): WebPushSender
    {
        return new class($sent) extends WebPushSender {
            public function __construct(public array &$sent)
            {
            }

            public function sendToUsers(iterable $users, array $payload): void
            {
                foreach ($users as $user) {
                    $this->sent[] = $user->id;
                }
            }
        };
    }

    public function test_paying_a_private_bill_tells_no_one(): void
    {
        [$payer] = $this->family();

        $bill = Bill::create([
            'name' => 'Birthday present', 'category_id' => Category::create(['name' => 'Gifts'])->id,
            'amount' => 80, 'currency_code' => 'EUR', 'frequency' => 'once',
            'start_date' => now()->toDateString(), 'next_due_date' => now()->toDateString(),
            'created_by' => $payer->id, 'is_shared' => false, 'is_active' => true,
        ]);
        $payment = Payment::create([
            'bill_id' => $bill->id, 'paid_by' => $payer->id, 'amount' => 80,
            'currency_code' => 'EUR', 'paid_at' => now(),
        ]);

        $sent = [];
        (new SendPaymentPushNotification($payment->id))->handle($this->recordingSender($sent));

        $this->assertSame([], $sent);
    }

    public function test_a_push_endpoint_inside_the_network_is_refused(): void
    {
        $this->app->bind(SafeUrlFetcher::class, fn () => new class extends SafeUrlFetcher {
            protected function resolve(string $host): array
            {
                return $host === 'push.example.com' ? ['93.184.216.34'] : ['10.0.0.5'];
            }
        });
        $user = User::factory()->create();
        $keys = ['p256dh' => 'key', 'auth' => 'auth'];

        $this->actingAs($user)->postJson(route('push.subscribe'), ['endpoint' => 'https://nas.lan/hook', 'keys' => $keys])
            ->assertStatus(422);
        $this->actingAs($user)->postJson(route('push.subscribe'), ['endpoint' => 'http://push.example.com/x', 'keys' => $keys])
            ->assertStatus(422);
        $this->assertDatabaseCount('push_subscriptions', 0);

        $this->actingAs($user)->postJson(route('push.subscribe'), ['endpoint' => 'https://push.example.com/x', 'keys' => $keys])
            ->assertOk();
        $this->assertSame(1, PushSubscription::count());
    }

    public function test_a_product_page_shows_only_this_households_shopping(): void
    {
        [$me] = $this->family();
        $neighbour = User::factory()->create(['name' => 'Neighbour Name']);
        $product = Product::create(['name' => 'Milk', 'unit' => 'piece', 'default_quantity' => 1]);
        $theirList = ShoppingList::create(['name' => 'Secret party list', 'user_id' => $neighbour->id]);
        $theirList->items()->create(['name' => 'Milk', 'product_id' => $product->id, 'quantity' => 1, 'unit' => 'piece']);
        ProductPurchase::create([
            'product_id' => $product->id, 'shopping_list_id' => $theirList->id,
            'quantity' => 1, 'unit' => 'piece', 'purchased_at' => now(), 'purchased_by' => $neighbour->id,
        ]);

        $this->actingAs($me)->get(route('products.show', $product))
            ->assertOk()
            ->assertDontSee('Neighbour Name')
            ->assertDontSee('Secret party list');
    }

    public function test_a_barcode_that_is_not_digits_never_reaches_the_api(): void
    {
        Http::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/shopping-lists/lookup-barcode', ['barcode' => '../../cgi/search.pl?x=1'])
            ->assertNotFound();

        Http::assertNothingSent();
    }
}
