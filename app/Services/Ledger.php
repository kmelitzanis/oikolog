<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Income;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The single place account movements are written. Everything that moves money
 * — receiving an income, paying a bill, moving cash between accounts — goes
 * through here, so the ledger stays the one description of what happened.
 */
class Ledger
{
    /** Money arriving in an account. */
    public function deposit(
        Account $account,
        float $amount,
        Carbon $occurredAt,
        string $userId,
        ?string $description = null,
        ?Income $income = null,
    ): AccountTransaction {
        return $this->record($account, 'deposit', $amount, 1, $occurredAt, $userId, $description, [
            'income_id' => $income?->id,
        ]);
    }

    /** Money leaving an account. */
    public function withdraw(
        Account $account,
        float $amount,
        Carbon $occurredAt,
        string $userId,
        ?string $description = null,
        ?Payment $payment = null,
    ): AccountTransaction {
        return $this->record($account, 'withdrawal', $amount, -1, $occurredAt, $userId, $description, [
            'payment_id' => $payment?->id,
        ]);
    }

    /**
     * Move money between two accounts as one atomic pair of rows sharing a
     * `transfer_group` — that pairing is what lets the UI show a transfer as a
     * single event and undo it as one.
     *
     * @return array{0: AccountTransaction, 1: AccountTransaction} [out, in]
     */
    public function transfer(
        Account $from,
        Account $to,
        float $amount,
        Carbon $occurredAt,
        string $userId,
        ?string $description = null,
    ): array {
        $group = (string) Str::ulid();

        return DB::transaction(function () use ($from, $to, $amount, $occurredAt, $userId, $description, $group) {
            $out = $this->record($from, 'transfer_out', $amount, -1, $occurredAt, $userId, $description, [
                'transfer_group' => $group,
            ]);
            $in = $this->record($to, 'transfer_in', $amount, 1, $occurredAt, $userId, $description, [
                'transfer_group' => $group,
            ]);

            return [$out, $in];
        });
    }

    /** Remove both halves of a transfer. */
    public function reverseTransfer(AccountTransaction $leg): void
    {
        DB::transaction(function () use ($leg) {
            AccountTransaction::where('transfer_group', $leg->transfer_group)->delete();
        });
    }

    /** Drop the movement a payment created, if it had one. */
    public function reversePayment(Payment $payment): void
    {
        AccountTransaction::where('payment_id', $payment->id)->delete();
    }

    private function record(
        Account $account,
        string $type,
        float $amount,
        int $direction,
        Carbon $occurredAt,
        string $userId,
        ?string $description,
        array $extra = [],
    ): AccountTransaction {
        return AccountTransaction::create([
            'account_id' => $account->id,
            'type' => $type,
            'amount' => round(abs($amount), 2),
            'direction' => $direction,
            'currency_code' => $account->currency_code,
            'occurred_at' => $occurredAt,
            'description' => $description,
            'created_by' => $userId,
            ...$extra,
        ]);
    }
}
