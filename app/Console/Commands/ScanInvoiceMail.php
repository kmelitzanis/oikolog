<?php

namespace App\Console\Commands;

use App\Models\Mailbox;
use App\Services\InvoiceMailScanner;
use Illuminate\Console\Command;

class ScanInvoiceMail extends Command
{
    protected $signature = 'bills:scan-mail
                            {--user= : Only scan this user id}
                            {--days=30 : How far back to look}';

    protected $description = 'Read provider invoice emails and queue amount suggestions for review';

    public function handle(InvoiceMailScanner $scanner): int
    {
        $mailboxes = Mailbox::with('user')->where('is_active', true)
            ->when($this->option('user'), fn ($q, $id) => $q->where('user_id', $id))
            ->get();

        if ($mailboxes->isEmpty()) {
            $this->info('No active mailboxes configured.');

            return self::SUCCESS;
        }

        foreach ($mailboxes as $mailbox) {
            $this->line("Scanning {$mailbox->username}…");

            $r = $scanner->scan($mailbox, (int) $this->option('days'));

            if ($r['error']) {
                $this->error("  {$r['error']}");
                continue;
            }

            $this->info("  {$r['scanned']} read · {$r['matched']} matched · {$r['created']} new suggestion(s)");
        }

        return self::SUCCESS;
    }
}
