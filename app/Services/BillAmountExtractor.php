<?php

namespace App\Services;

use App\Models\Provider;

/**
 * Pulls the amount due out of a provider's invoice email.
 *
 * Each provider carries its own regexes: one to recognise the sender, one for
 * the subject, and one whose first capture group is the total. Everything is
 * per-provider on purpose — "the number after the euro sign" is not a rule that
 * survives contact with real invoice mail, where the same message also quotes
 * last month's total, a VAT line and an account number.
 */
class BillAmountExtractor
{
    /** Whether this email looks like it came from this provider. */
    public function matches(Provider $provider, string $from, string $subject): bool
    {
        $fromPattern = $provider->email_from_pattern;
        $subjectPattern = $provider->email_subject_pattern;

        // A provider with no sender pattern can't be recognised at all — better
        // to skip it than to test its amount regex against every email.
        if (blank($fromPattern)) {
            return false;
        }

        if (! $this->test($fromPattern, $from)) {
            return false;
        }

        // The subject pattern is the optional second gate: it separates "your
        // bill is ready" from the provider's marketing.
        return blank($subjectPattern) || $this->test($subjectPattern, $subject);
    }

    /**
     * Returns ['amount' => float, 'excerpt' => string] or null.
     *
     * The excerpt is the matched fragment, kept so a human reviewing the
     * suggestion can see what the regex actually latched onto.
     */
    public function extract(Provider $provider, string $text): ?array
    {
        $pattern = $provider->email_amount_pattern;

        if (blank($pattern)) {
            return null;
        }

        $normalised = $this->normaliseWhitespace($text);

        if (! @preg_match($this->delimit($pattern), $normalised, $m)) {
            return null;
        }

        $raw = $m[1] ?? $m[0] ?? null;

        if ($raw === null) {
            return null;
        }

        $amount = $this->toFloat($raw);

        if ($amount === null || $amount <= 0) {
            return null;
        }

        return [
            'amount'  => $amount,
            'excerpt' => mb_substr($this->contextAround($normalised, $m[0]), 0, 300),
        ];
    }

    /**
     * Greek invoices write 1.234,56 — dot for thousands, comma for decimals.
     * English ones write 1,234.56. Decide from whichever separator comes last.
     */
    public function toFloat(string $raw): ?float
    {
        $clean = preg_replace('/[^\d.,-]/u', '', $raw);

        if ($clean === '' || $clean === null) {
            return null;
        }

        $lastComma = strrpos($clean, ',');
        $lastDot   = strrpos($clean, '.');

        if ($lastComma !== false && ($lastDot === false || $lastComma > $lastDot)) {
            $clean = str_replace('.', '', $clean);      // thousands
            $clean = str_replace(',', '.', $clean);     // decimal
        } else {
            $clean = str_replace(',', '', $clean);      // thousands
        }

        return is_numeric($clean) ? round((float) $clean, 2) : null;
    }

    /** A user-supplied regex arrives without delimiters; add them safely. */
    private function delimit(string $pattern): string
    {
        return '/' . str_replace('/', '\/', $pattern) . '/iu';
    }

    private function test(string $pattern, string $subject): bool
    {
        return (bool) @preg_match($this->delimit($pattern), $subject);
    }

    /** HTML mail and PDFs-as-text arrive full of newlines and nbsp. */
    private function normaliseWhitespace(string $text): string
    {
        $text = str_replace("\xC2\xA0", ' ', $text);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function contextAround(string $haystack, string $needle): string
    {
        $pos = mb_strpos($haystack, $needle);

        if ($pos === false) {
            return $needle;
        }

        return mb_substr($haystack, max(0, $pos - 80), mb_strlen($needle) + 160);
    }
}
