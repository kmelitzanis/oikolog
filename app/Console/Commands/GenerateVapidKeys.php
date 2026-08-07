<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeys extends Command
{
    protected $signature = 'push:vapid';

    protected $description = 'Generate a VAPID key pair for Web Push and print the .env lines';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->newLine();
        $this->line('Add these to your .env, then run `php artisan config:clear`:');
        $this->newLine();
        $this->line('VAPID_SUBJECT=mailto:you@example.com');
        $this->line('VAPID_PUBLIC_KEY=' . $keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY=' . $keys['privateKey']);
        $this->newLine();
        $this->warn('Changing these invalidates every existing push subscription.');

        return self::SUCCESS;
    }
}
