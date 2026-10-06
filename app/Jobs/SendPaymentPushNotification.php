<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Models\User;
use App\Services\WebPushSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * "Ο Κώστας πλήρωσε ΑΦΥΓΡΑΝΤΗΡΑΣ — 120,00 €", delivered to the payer's family.
 *
 * Dispatched with ->afterResponse() from the pay flow: the person paying should
 * not wait on the push services, and nobody should have to run a queue worker
 * for notifications to arrive.
 */
class SendPaymentPushNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $paymentId)
    {
    }

    public function handle(WebPushSender $sender): void
    {
        $payment = Payment::with(['bill', 'paidBy'])->find($this->paymentId);

        if (! $payment || ! $payment->bill || ! $payment->paidBy) {
            return;
        }

        // Only a bill the household shares is the household's business. A
        // private bill's name and amount must not be announced to everyone
        // else in the family just because one of them paid it.
        if (! $payment->bill->is_shared || ! $payment->bill->family_id) {
            return;
        }

        $payer = $payment->paidBy;
        $recipients = $this->recipients($payer, $payment->bill->family_id);

        if ($recipients->isEmpty()) {
            return;
        }

        $amount = $payment->currency_code . ' ' . number_format((float) $payment->amount, 2);

        // Each recipient may read the app in a different language, so the copy is
        // built per recipient rather than once in the sender's locale.
        foreach ($recipients as $recipient) {
            $locale = $recipient->locale ?: config('app.locale');

            $title = trans('messages.push_payment_title', [
                'who'  => $payer->subjectName($locale),
                'bill' => $payment->bill->name,
            ], $locale);

            $body = $payment->is_partial
                ? trans('messages.push_payment_body_partial', ['amount' => $amount], $locale)
                : trans('messages.push_payment_body', ['amount' => $amount], $locale);

            $sender->sendToUsers([$recipient], [
                'title' => $title,
                'body'  => $body,
                // Deep link straight to the payment row in the bill's history.
                'url'   => route('bills.show', $payment->bill) . '?payment=' . $payment->id . '#payment-' . $payment->id,
                'tag'   => 'payment-' . $payment->id,
            ]);
        }
    }

    /**
     * Everyone in the bill's family except the payer — telling someone about
     * their own action is noise.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function recipients(User $payer, string $familyId)
    {
        return User::where('family_id', $familyId)
            ->whereKeyNot($payer->id)
            ->where('notifications_enabled', true)
            ->get();
    }
}
