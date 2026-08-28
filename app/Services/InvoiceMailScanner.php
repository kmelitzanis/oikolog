<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\BillAmountSuggestion;
use App\Models\Mailbox;
use App\Models\Provider;
use Illuminate\Support\Facades\Log;
use Webklex\PHPIMAP\ClientManager;

/**
 * Reads a user's mailbox and queues amount suggestions for their bills.
 *
 * Read only, and deliberately conservative: it opens the folder without the
 * peek flag disabled (messages stay unread), never deletes, and writes nothing
 * to any bill. A parsed figure becomes a pending suggestion; a person accepts
 * it. Provider regexes are configuration, so every failure mode here is a
 * skipped email rather than a wrong number on a bill.
 */
class InvoiceMailScanner
{
    public function __construct(private BillAmountExtractor $extractor)
    {
    }

    /**
     * @return array{scanned: int, matched: int, created: int, error: ?string}
     */
    public function scan(Mailbox $mailbox, int $sinceDays = 30): array
    {
        $result = ['scanned' => 0, 'matched' => 0, 'created' => 0, 'error' => null];

        // Only bills that can actually receive a figure: a varying cost, an
        // active schedule, and a provider that knows how to be recognised.
        $bills = Bill::forUser($mailbox->user)
            ->active()
            ->where('cost_varies', true)
            ->whereNotNull('provider_id')
            ->with('provider')
            ->get()
            ->filter(fn (Bill $b) => $b->provider && filled($b->provider->email_from_pattern));

        if ($bills->isEmpty()) {
            $mailbox->forceFill(['last_scanned_at' => now(), 'last_error' => null])->save();

            return $result;
        }

        try {
            $folder = $this->openFolder($mailbox);

            $messages = $folder->query()
                ->since(now()->subDays($sinceDays))
                ->leaveUnread()
                ->limit(200)
                ->get();
        } catch (\Throwable $e) {
            $message = 'IMAP: ' . $e->getMessage();
            Log::warning("Mailbox {$mailbox->id} scan failed. " . $e->getMessage());
            $mailbox->forceFill(['last_scanned_at' => now(), 'last_error' => mb_substr($message, 0, 255)])->save();

            return $result + ['error' => $message];
        }

        foreach ($messages as $message) {
            $result['scanned']++;

            try {
                $from    = (string) ($message->getFrom()[0]->mail ?? '');
                $subject = (string) $message->getSubject();
                $uid     = (string) $message->getUid();
                $body    = $this->bodyText($message);
            } catch (\Throwable $e) {
                continue; // A single unreadable message must not stop the scan.
            }

            foreach ($bills as $bill) {
                /** @var Provider $provider */
                $provider = $bill->provider;

                if (! $this->extractor->matches($provider, $from, $subject)) {
                    continue;
                }

                $result['matched']++;

                // The total can sit in either half; the subject often carries it
                // outright ("Ο λογαριασμός σας: 108,45 €").
                $found = $this->extractor->extract($provider, $subject)
                      ?? $this->extractor->extract($provider, $body);

                if (! $found) {
                    continue;
                }

                $created = BillAmountSuggestion::firstOrCreate(
                    ['bill_id' => $bill->id, 'message_uid' => $uid],
                    [
                        'amount'       => $found['amount'],
                        'status'       => 'pending',
                        'subject'      => mb_substr($subject, 0, 255),
                        'from_address' => mb_substr($from, 0, 255),
                        'email_date'   => optional($message->getDate())->first() ?? now(),
                        'excerpt'      => $found['excerpt'],
                    ],
                );

                if ($created->wasRecentlyCreated) {
                    $result['created']++;
                }

                break; // One email belongs to one bill.
            }
        }

        $mailbox->forceFill(['last_scanned_at' => now(), 'last_error' => null])->save();

        return $result;
    }

    /** Opens the configured folder, throwing on bad credentials or host. */
    public function openFolder(Mailbox $mailbox)
    {
        $client = (new ClientManager())->make([
            'host'          => $mailbox->host,
            'port'          => $mailbox->port,
            'encryption'    => $mailbox->encryption === 'none' ? false : $mailbox->encryption,
            'validate_cert' => true,
            'username'      => $mailbox->username,
            'password'      => $mailbox->password,
            'protocol'      => 'imap',
        ]);

        $client->connect();

        return $client->getFolderByPath($mailbox->folder);
    }

    /** Prefer the text part; fall back to HTML with the tags stripped. */
    private function bodyText($message): string
    {
        $text = (string) $message->getTextBody();

        if (trim($text) !== '') {
            return $text;
        }

        return trim(strip_tags((string) $message->getHTMLBody()));
    }
}
