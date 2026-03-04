<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ResetAdminPassword extends Command
{
    protected $signature   = 'admin:reset-password {--email= : Admin email} {--password= : New password}';
    protected $description = 'Reset the admin user password';

    public function handle(): int
    {
        $email    = $this->option('email') ?? env('ADMIN_EMAIL', 'admin@billstrack.local');
        $password = $this->option('password') ?? env('ADMIN_PASSWORD', 'changeme123');

        $user = User::where('email', $email)->first();

        if (! $user) {
            // No user exists — create one
            $user = User::create([
                'name'          => 'Admin',
                'email'         => $email,
                'password'      => $password,
                'currency_code' => 'EUR',
            ]);
            $this->info("Created admin user: {$email}");
        } else {
            $user->password = $password;
            $user->save();
            $this->info("Password reset for: {$email}");
        }

        $this->line("Email:    {$email}");
        $this->line("Password: {$password}");

        return self::SUCCESS;
    }
}

