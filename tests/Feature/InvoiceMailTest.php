<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\BillAmountSuggestion;
use App\Models\Category;
use App\Models\Provider;
use App\Models\User;
use App\Services\BillAmountExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceMailTest extends TestCase
{
    use RefreshDatabase;

    private function provider(array $attrs = []): Provider
    {
        return Provider::forceCreate(array_merge([
            'name'                  => 'ΔΕΗ',
            'category_id'           => Category::create(['name' => 'Energy'])->id,
            'email_from_pattern'    => '@dei\.gr$',
            'email_subject_pattern' => 'λογαριασμ',
            'email_amount_pattern'  => 'πληρωμ\S*\s*:?\s*([\d.,]+)\s*€',
        ], $attrs));
    }

    private function bill(User $user, Provider $provider): Bill
    {
        return Bill::create([
            'name'          => 'ΔΕΗ',
            'category_id'   => Category::create(['name' => 'Utilities'])->id,
            'provider_id'   => $provider->id,
            'created_by'    => $user->id,
            'amount'        => 0,
            'cost_varies'   => true,
            'currency_code' => 'EUR',
            'frequency'     => 'monthly',
            'start_date'    => now()->subMonth()->toDateString(),
            'next_due_date' => now()->addDays(10)->toDateString(),
            'is_active'     => true,
        ]);
    }

    // ── Recognising the mail ────────────────────────────────────────────

    public function test_sender_and_subject_gate_the_match(): void
    {
        $extractor = new BillAmountExtractor();
        $provider = $this->provider();

        $this->assertTrue($extractor->matches($provider, 'no-reply@dei.gr', 'Ο λογαριασμός σας'));
        // Right sender, wrong subject — marketing mail must not match.
        $this->assertFalse($extractor->matches($provider, 'no-reply@dei.gr', 'Νέα προσφορά'));
        // Look-alike domain.
        $this->assertFalse($extractor->matches($provider, 'billing@notdei.gr.evil.com', 'Ο λογαριασμός σας'));
    }

    public function test_a_provider_without_a_sender_pattern_never_matches(): void
    {
        $extractor = new BillAmountExtractor();
        $provider = $this->provider(['email_from_pattern' => null]);

        $this->assertFalse($extractor->matches($provider, 'no-reply@dei.gr', 'Ο λογαριασμός σας'));
    }

    // ── Reading the number ──────────────────────────────────────────────

    public function test_it_takes_the_capture_group_not_the_first_number(): void
    {
        $extractor = new BillAmountExtractor();
        $provider = $this->provider();

        // The mail also quotes an account number and last month's total.
        $body = 'Αρ. παροχής 12345678. Προηγούμενη χρέωση 95,10 €. Ποσό πληρωμής: 108,45 € έως 30/09.';

        $found = $extractor->extract($provider, $body);

        $this->assertSame(108.45, $found['amount']);
        $this->assertStringContainsString('108,45', $found['excerpt']);
    }

    /** Greek writes 1.234,56; English writes 1,234.56. */
    public function test_it_reads_both_decimal_conventions(): void
    {
        $extractor = new BillAmountExtractor();

        $this->assertSame(1234.56, $extractor->toFloat('1.234,56'));
        $this->assertSame(1234.56, $extractor->toFloat('1,234.56'));
        $this->assertSame(108.45, $extractor->toFloat('108,45'));
        $this->assertSame(108.45, $extractor->toFloat('108.45'));
        $this->assertSame(108.0, $extractor->toFloat('108'));
        $this->assertNull($extractor->toFloat('—'));
    }

    public function test_a_broken_regex_yields_nothing_rather_than_throwing(): void
    {
        $extractor = new BillAmountExtractor();
        $provider = $this->provider(['email_amount_pattern' => '([unclosed']);

        $this->assertNull($extractor->extract($provider, 'Ποσό πληρωμής: 108,45 €'));
    }

    public function test_a_zero_or_negative_total_is_not_a_suggestion(): void
    {
        $extractor = new BillAmountExtractor();
        $provider = $this->provider();

        $this->assertNull($extractor->extract($provider, 'Ποσό πληρωμής: 0,00 €'));
    }

    // ── Accepting ───────────────────────────────────────────────────────

    public function test_accepting_writes_the_amount_and_rejecting_does_not(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR']);
        $bill = $this->bill($user, $this->provider());

        $suggestion = BillAmountSuggestion::create([
            'bill_id' => $bill->id, 'amount' => 108.45, 'message_uid' => '1',
        ]);

        $this->actingAs($user)
            ->post(route('bills.suggestions.accept', [$bill, $suggestion]))
            ->assertRedirect();

        $this->assertSame(108.45, $bill->fresh()->periodAmount());
        $this->assertSame('accepted', $suggestion->fresh()->status);
    }

    public function test_rejecting_leaves_the_bill_untouched(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR']);
        $bill = $this->bill($user, $this->provider());

        $suggestion = BillAmountSuggestion::create([
            'bill_id' => $bill->id, 'amount' => 999.99, 'message_uid' => '1',
        ]);

        $this->actingAs($user)
            ->post(route('bills.suggestions.reject', [$bill, $suggestion]))
            ->assertRedirect();

        $this->assertNull($bill->fresh()->current_amount);
        $this->assertSame('rejected', $suggestion->fresh()->status);
    }

    public function test_accepting_supersedes_other_pending_suggestions(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR']);
        $bill = $this->bill($user, $this->provider());

        $old = BillAmountSuggestion::create(['bill_id' => $bill->id, 'amount' => 95.10, 'message_uid' => '1']);
        $new = BillAmountSuggestion::create(['bill_id' => $bill->id, 'amount' => 108.45, 'message_uid' => '2']);

        $this->actingAs($user)->post(route('bills.suggestions.accept', [$bill, $new]))->assertRedirect();

        $this->assertSame('rejected', $old->fresh()->status);
    }

    public function test_a_stranger_cannot_accept(): void
    {
        $owner = User::factory()->create(['currency_code' => 'EUR']);
        $other = User::factory()->create(['currency_code' => 'EUR']);
        $bill = $this->bill($owner, $this->provider());

        $suggestion = BillAmountSuggestion::create([
            'bill_id' => $bill->id, 'amount' => 108.45, 'message_uid' => '1',
        ]);

        $this->actingAs($other)
            ->post(route('bills.suggestions.accept', [$bill, $suggestion]))
            ->assertForbidden();

        $this->assertNull($bill->fresh()->current_amount);
    }

    public function test_a_resolved_suggestion_cannot_be_accepted_twice(): void
    {
        $user = User::factory()->create(['currency_code' => 'EUR']);
        $bill = $this->bill($user, $this->provider());

        $suggestion = BillAmountSuggestion::create([
            'bill_id' => $bill->id, 'amount' => 108.45, 'message_uid' => '1', 'status' => 'accepted',
        ]);

        $this->actingAs($user)
            ->post(route('bills.suggestions.accept', [$bill, $suggestion]))
            ->assertStatus(409);
    }
}
