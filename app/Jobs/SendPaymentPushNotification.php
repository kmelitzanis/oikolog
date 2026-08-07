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

        $payer = $payment->paidBy;
        $recipients = $this->recipients($payer);

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
     * Everyone in the payer's family except the payer — telling someone about
     * their own action is noise. No family means no one to notify.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function recipients(User $payer)
    {
        if (! $payer->family_id) {
            return collect();
        }

        return User::where('family_id', $payer->family_id)
            ->whereKeyNot($payer->id)
            ->where('notifications_enabled', true)
            ->get();
    }
}
