<?php

namespace App\Services;

use App\Jobs\SendPaymentPushNotification;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Paying a bill and taking a payment back.
 *
 * The web page and the API used to do this separately, and the API copy had
 * fallen behind: it wrote no ledger movement, never drew a loan down and knew
 * nothing of partial payments. Both now come through here, so a payment means
 * the same thing whichever door it arrives by.
 */
class BillPayments
{
    public function __construct(private Ledger $ledger) {}

    /** Validation for a payment request, shared by both controllers. */
    public function rules(User $user): array
    {
        // The money has to come out of somewhere. Once the user keeps accounts,
        // picking one is required, otherwise balances quietly stop matching
        // reality. Users with no accounts yet can still record payments.
        $hasAccounts = Account::forUser($user)->active()->exists();

        // `paid_at` defaults to today but can be backdated, which matters
        // because it decides when the account balance actually moved. A bill
        // isn't paid before it's paid, so future dates are rejected.
        return [
            'account_id'      => [$hasAccounts ? 'required' : 'nullable', 'string', 'max:26'],
            'paid_by_user_id' => ['nullable', 'string', 'max:26'],
            'payment_mode'    => ['nullable', 'in:full,partial'],
            'partial_amount'  => ['nullable', 'required_if:payment_mode,partial', 'numeric', 'min:0.01', 'max:99999999'],
            'custom_amount'   => ['nullable', 'numeric', 'min:0.01', 'max:99999999'],
            'paid_at'         => ['nullable', 'date', 'before_or_equal:today'],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Record a payment against the bill's current cycle.
     *
     * @param  array  $data  validated against rules()
     * @return array{0: Payment, 1: bool} the payment, and whether it left the cycle part-paid
     */
    public function pay(Bill $bill, User $user, array $data): array
    {
        // Only an account this user can reach, and only someone in their
        // household as the payer: both arrive from the client.
        $account = null;
        if (! empty($data['account_id'])) {
            $account = Account::forUser($user)->find($data['account_id']);
            if (! $account) {
                throw ValidationException::withMessages(['account_id' => __('messages.invalid_selection')]);
            }
        }

        $paidByUserId = $data['paid_by_user_id'] ?? $user->id;
        if ($paidByUserId !== $user->id
            && ! ($user->family_id && User::whereKey($paidByUserId)->where('family_id', $user->family_id)->exists())) {
            throw ValidationException::withMessages(['paid_by_user_id' => __('messages.invalid_selection')]);
        }

        $isPartial = ($data['payment_mode'] ?? 'full') === 'partial';
        $paidAt = ! empty($data['paid_at'])
            ? Carbon::parse($data['paid_at'])->setTimeFrom(now())
            : now();

        [$payment, $isPartial] = DB::transaction(function () use ($bill, $data, $paidByUserId, $account, $isPartial, $paidAt) {
            // Re-read the bill under a row lock: a double tap, or two people
            // paying at once, must see each other's payment rather than both
            // settling the same cycle from the same stale balance.
            $bill = Bill::whereKey($bill->getKey())->lockForUpdate()->firstOrFail();

            // periodAmount() prefers this cycle's recorded figure over the
            // template amount, which for a varying bill is only an estimate.
            $periodAmount = $bill->cost_varies && ! empty($data['custom_amount'])
                ? (float) $data['custom_amount']
                : $bill->periodAmount();

            $currentRemaining = $bill->remaining_balance !== null
                ? (float) $bill->remaining_balance
                : $periodAmount;

            if ($isPartial) {
                $partialAmount = (float) $data['partial_amount'];
                $newRemaining = round($currentRemaining - $partialAmount, 2);

                // A "partial" payment that covers what is left settles the cycle.
                if ($newRemaining <= 0) {
                    $isPartial = false;
                    $payAmount = $currentRemaining;
                    $newRemaining = null;
                } else {
                    $payAmount = $partialAmount;
                }
            } else {
                $payAmount = $currentRemaining;
                $newRemaining = null;
            }

            // The last instalment of a loan is whatever is left of it. Taking the
            // usual amount against a smaller balance would overpay the debt and
            // push it negative, so the payment is capped at the outstanding total.
            if ($bill->tracksDebt()) {
                $payAmount = min($payAmount, max(0.0, (float) $bill->debt_remaining));

                if ($payAmount <= 0) {
                    $newRemaining = null;
                    $isPartial = false;
                }
            }

            $payment = Payment::create([
                'bill_id'       => $bill->id,
                'paid_by'       => $paidByUserId,
                'account_id'    => $account?->id,
                'amount'        => $payAmount,
                'is_partial'    => $isPartial,
                'currency_code' => $bill->currency_code,
                'paid_at'       => $paidAt,
                'notes'         => $data['notes'] ?? null,
            ]);

            if ($account) {
                $this->ledger->withdraw($account, $payAmount, $paidAt, $paidByUserId, $bill->name, $payment);
            }

            if ($isPartial) {
                // Partial: update the remaining balance, do NOT advance the due date.
                $bill->update([
                    'remaining_balance' => $newRemaining,
                    'last_paid_date'    => $paidAt->toDateString(),
                ]);
            } else {
                $nextDue = $bill->calculateNextDueDate();
                $bill->update([
                    'remaining_balance' => null,
                    // A new cycle starts with its cost unknown again.
                    'current_amount'    => null,
                    'last_paid_date'    => $paidAt->toDateString(),
                    'next_due_date'     => $nextDue?->toDateString() ?? $bill->next_due_date,
                ]);
            }

            $this->drawDownDebt($bill, $payAmount);

            return [$payment, $isPartial];
        });

        // Tell the rest of the household. After the response so the person who
        // just paid isn't kept waiting on the push services.
        SendPaymentPushNotification::dispatch($payment->id)->afterResponse();

        return [$payment, $isPartial];
    }

    /**
     * Delete a payment and put the bill's derived state back where it belongs.
     *
     * Only the *latest* payment owns the current cycle, so only it may move
     * `next_due_date` or restore a partial balance. Deleting an older entry is a
     * history correction: it must not drag the schedule backwards, so it updates
     * `last_paid_date` and nothing else. The ledger row goes with the payment
     * (foreign key cascade).
     */
    public function remove(Bill $bill, Payment $payment): void
    {
        DB::transaction(function () use ($bill, $payment) {
            $latest    = $bill->payments()->latest('paid_at')->first();
            $wasLatest = $latest && $latest->getKey() === $payment->getKey();

            $wasPartial   = $payment->is_partial;
            $undoneAmount = (float) $payment->amount;

            $payment->delete();

            // Whatever the payment settled, the money is owed again. Done here
            // rather than per branch below: each of them returns early.
            $this->restoreDebt($bill, $undoneAmount);

            // Whatever remains is the new "last paid", null when none is left.
            $prevPayment = $bill->payments()->latest('paid_at')->first();

            if (! $wasLatest) {
                $bill->update(['last_paid_date' => $prevPayment?->paid_at?->toDateString()]);

                return;
            }

            if ($wasPartial) {
                // Give the money back to the outstanding balance. Once it covers
                // the full amount again there is no partial state left to track.
                $currentRemaining  = $bill->remaining_balance !== null ? (float) $bill->remaining_balance : 0;
                $restoredRemaining = round($currentRemaining + $undoneAmount, 2);

                // Against this cycle's figure, not the template amount: on a
                // varying bill the two differ, and the bill was left "partial"
                // with nothing paid.
                if ($restoredRemaining >= $bill->periodAmount()) {
                    $restoredRemaining = null;
                }

                $bill->update([
                    'remaining_balance' => $restoredRemaining,
                    'last_paid_date'    => $prevPayment?->paid_at?->toDateString(),
                ]);

                return;
            }

            // A full payment had advanced the schedule by one cycle; undoing it
            // steps back one cycle. This used to set the due date to the day
            // the payment was made, which moved the schedule (paid early on
            // the 3rd, a bill due on the 5th came back due on the 3rd) and
            // for a one-off, which never advanced, invented a new due date.
            $previousDue = $bill->next_due_date ? $bill->retreatDate($bill->next_due_date) : null;

            $bill->update([
                // Partial payments never advance the cycle, so a partial one
                // right before this payment belongs to the cycle it closed:
                // what this payment covered is owed again, not the full amount.
                'remaining_balance' => $prevPayment?->is_partial ? round($undoneAmount, 2) : null,
                'last_paid_date'    => $prevPayment?->paid_at?->toDateString(),
                'next_due_date'     => ($previousDue ?? $bill->next_due_date)?->toDateString(),
            ]);
        });
    }

    /**
     * Take a payment off the outstanding debt, and retire the bill once it is
     * cleared.
     *
     * A loan is not a subscription: it has an end. When the last instalment
     * lands the schedule has done its job, so the bill is deactivated rather
     * than rolled forward into a cycle that will never be owed.
     */
    private function drawDownDebt(Bill $bill, float $paidAmount): void
    {
        if (! $bill->tracksDebt() || $paidAmount <= 0) {
            return;
        }

        $remaining = round(max(0.0, (float) $bill->debt_remaining - $paidAmount), 2);

        $bill->update([
            'debt_remaining' => $remaining,
            'is_active'      => $remaining > 0 ? $bill->is_active : false,
        ]);
    }

    /** Put an undone payment back onto the debt, and revive a retired bill. */
    private function restoreDebt(Bill $bill, float $undoneAmount): void
    {
        if (! $bill->tracksDebt() || $undoneAmount <= 0) {
            return;
        }

        $remaining = round((float) $bill->debt_remaining + $undoneAmount, 2);

        if ($bill->debt_initial !== null) {
            $remaining = min($remaining, (float) $bill->debt_initial);
        }

        $bill->update([
            'debt_remaining' => $remaining,
            // Undoing the final instalment means the loan is owed again.
            'is_active'      => $remaining > 0 ? true : $bill->is_active,
        ]);
    }
}
