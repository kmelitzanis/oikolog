<?php

namespace Tests\Feature;

use App\Jobs\SendPaymentPushNotification;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Family;
use App\Models\Payment;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\WebPushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PaymentPushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_greek_subject_name_takes_the_gendered_article(): void
    {
        $male = new User(['name' => 'Κώστας', 'gender' => 'male', 'locale' => 'el']);
        $female = new User(['name' => 'Μαρία', 'gender' => 'female', 'locale' => 'el']);
        $unset = new User(['name' => 'Άλεξ', 'locale' => 'el']);
        $english = new User(['name' => 'Kostas', 'gender' => 'male', 'locale' => 'en']);

        $this->assertSame('Ο Κώστας', $male->subjectName());
        $this->assertSame('Η Μαρία', $female->subjectName());
        $this->assertSame('Ο/Η Άλεξ', $unset->subjectName());
        $this->assertSame('Kostas', $english->subjectName());
    }

    public function test_notification_title_reads_as_a_sentence(): void
    {
        $title = trans('messages.push_payment_title', [
            'who'  => (new User(['name' => 'Κώστας', 'gender' => 'male', 'locale' => 'el']))->subjectName(),
            'bill' => 'ΑΦΥΓΡΑΝΤΗΡΑΣ - ΤΟΣΤΙΕΡΑ - MIXER',
        ], 'el');

        $this->assertSame('Ο Κώστας πλήρωσε ΑΦΥΓΡΑΝΤΗΡΑΣ - ΤΟΣΤΙΕΡΑ - MIXER', $title);
    }

    public function test_paying_a_bill_queues_a_notification(): void
    {
        Queue::fake();

        [$payer, $bill] = $this->familyBill();

        $this->actingAs($payer)
            ->post(route('bills.pay', $bill), ['payment_mode' => 'full'])
            ->assertRedirect();

        Queue::assertPushed(SendPaymentPushNotification::class);
    }

    public function test_the_payer_is_not_notified_but_the_family_is(): void
    {
        [$payer, $bill] = $this->familyBill();

        $partner = User::factory()->create([
            'family_id' => $payer->family_id,
            'locale'    => 'el',
        ]);

        $payment = Payment::create([
            'bill_id'       => $bill->id,
            'paid_by'       => $payer->id,
            'amount'        => 120,
            'currency_code' => 'EUR',
            'paid_at'       => now(),
        ]);

        // Both have a device registered; only the partner should be sent to.
        foreach ([$payer, $partner] as $user) {
            PushSubscription::create([
                'user_id'       => $user->id,
                'endpoint'      => 'https://push.example/' . $user->id,
                'endpoint_hash' => PushSubscription::hashFor('https://push.example/' . $user->id),
                'public_key'    => 'key',
                'auth_token'    => 'auth',
            ]);
        }

        $sent = [];
        $sender = new class($sent) extends WebPushSender {
            public function __construct(public array &$sent)
            {
            }

            public function sendToUsers(iterable $users, array $payload): void
            {
                foreach ($users as $user) {
                    $this->sent[] = ['user' => $user->id, 'payload' => $payload];
                }
            }
        };

        (new SendPaymentPushNotification($payment->id))->handle($sender);

        $this->assertCount(1, $sent);
        $this->assertSame($partner->id, $sent[0]['user']);
        $this->assertStringContainsString($bill->name, $sent[0]['payload']['title']);
        // The deep link must carry the payment, not just the bill.
        $this->assertStringContainsString('payment=' . $payment->id, $sent[0]['payload']['url']);
        $this->assertStringContainsString('#payment-' . $payment->id, $sent[0]['payload']['url']);
    }

    public function test_the_deep_link_highlights_the_payment_row(): void
    {
        [$payer, $bill] = $this->familyBill();

        $payment = Payment::create([
            'bill_id'       => $bill->id,
            'paid_by'       => $payer->id,
            'amount'        => 120,
            'currency_code' => 'EUR',
            'paid_at'       => now(),
        ]);

        $this->actingAs($payer)
            ->get(route('bills.show', $bill) . '?payment=' . $payment->id)
            ->assertOk()
            ->assertSee('id="payment-' . $payment->id . '"', false)
            ->assertSee('ring-amber-300', false);
    }

    public function test_settings_stores_the_gender(): void
    {
        $user = User::factory()->create(['locale' => 'el']);

        $this->actingAs($user)->post(route('settings.update'), [
            'name'          => $user->name,
            'email'         => $user->email,
            'currency_code' => 'EUR',
            'gender'        => 'female',
        ])->assertRedirect();

        $this->assertSame('female', $user->fresh()->gender);
    }

    /** @return array{0: User, 1: Bill} */
    private function familyBill(): array
    {
        $payer = User::factory()->create(['gender' => 'male', 'locale' => 'el']);
        $family = Family::create(['name' => 'Test', 'owner_id' => $payer->id]);
        $payer->update(['family_id' => $family->id, 'family_role' => 'owner']);

        $bill = Bill::create([
            'name'          => 'ΑΦΥΓΡΑΝΤΗΡΑΣ - ΤΟΣΤΙΕΡΑ - MIXER',
            'category_id'   => Category::create(['name' => 'Home', 'icon' => 'home'])->id,
            'amount'        => 120,
            'currency_code' => 'EUR',
            'frequency'     => 'monthly',
            'start_date'    => now()->toDateString(),
            'next_due_date' => now()->toDateString(),
            'created_by'    => $payer->id,
            'family_id'     => $family->id,
            'is_shared'     => true,
            'is_active'     => true,
        ]);

        return [$payer->refresh(), $bill];
    }
}
