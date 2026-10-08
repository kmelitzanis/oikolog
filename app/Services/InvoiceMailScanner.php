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
    public function __construct(
        private BillAmountExtractor $extractor,
        private MicrosoftMailAuth $microsoft,
    ) {
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

    /**
     * Turn a provider's refusal into something the user can act on.
     *
     * Microsoft switched Basic Authentication off for Outlook.com and Exchange
     * Online, and Google did the same for plain passwords: both now answer a
     * correct username and password with a flat refusal. The raw IMAP line
     * ("NO Basic authentication is disabled") reads like a wrong password, so
     * people retype credentials that were never the problem.
     */
    public static function explainError(string $message): string
    {
        $m = strtolower($message);

        if (str_contains($m, 'basic authentication is disabled')
            || str_contains($m, 'authenticate failed')
            || str_contains($m, 'basicauth')) {
            return __('messages.mailbox_basic_auth_disabled');
        }

        // Gmail answers a normal password with this; an app password works.
        if (str_contains($m, 'invalid credentials') || str_contains($m, 'web login required')) {
            return __('messages.mailbox_gmail_app_password');
        }

        if (str_contains($m, 'application-specific password')
            || str_contains($m, 'app password')) {
            return __('messages.mailbox_needs_app_password');
        }

        return $message;
    }

    /** Opens the configured folder, throwing on bad credentials or host. */
    public function openFolder(Mailbox $mailbox)
    {
        // A Microsoft-connected mailbox logs in with XOAUTH2: the "password"
        // is a fresh access token, refreshed here when it is about to expire.
        $config = $mailbox->usesMicrosoft()
            ? [
                'host'           => MicrosoftMailAuth::IMAP_HOST,
                'port'           => 993,
                'encryption'     => 'ssl',
                'authentication' => 'oauth',
                'password'       => $this->microsoft->accessToken($mailbox),
            ]
            : [
                'host'       => $mailbox->host,
                'port'       => $mailbox->port,
                'encryption' => $mailbox->encryption === 'none' ? false : $mailbox->encryption,
                'password'   => $mailbox->password,
            ];

        $client = (new ClientManager())->make($config + [
            'validate_cert' => true,
            'username'      => $mailbox->username,
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
