<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ResetAdminPassword extends Command
{
    protected $signature   = 'admin:reset-password {--email= : Admin email} {--password= : New password}';
    protected $description = 'Reset the admin user password';

    public function handle(): int
    {
        // config(), not env(): env() is empty once the config is cached.
        $email = $this->option('email') ?: config('app.admin_email') ?: 'admin@oikolog.local';

        // Never fall back to a well-known default. Ask when someone is at the
        // keyboard, otherwise generate one and print it once.
        $password = $this->option('password');
        if (blank($password) && $this->input->isInteractive()) {
            $password = $this->secret('New password (leave empty to generate one)');
        }
        $generated = blank($password);
        if ($generated) {
            $password = Str::password(20, symbols: false);
        }

        if (mb_strlen($password) < 8) {
            $this->error('The password must be at least 8 characters.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $user = new User([
                'name'          => 'Admin',
                'email'         => $email,
                'currency_code' => 'EUR',
            ]);
            $user->password = $password;
            // Created as an admin — it is what this command is for.
            $user->is_admin = true;
            $user->save();
            $this->info("Created admin user: {$email}");
        } else {
            $user->password = $password;
            // Every "remember me" cookie issued under the old password dies.
            $user->setRememberToken(Str::random(60));
            $user->save();
            $this->info("Password reset for: {$email}");
        }

        $this->line("Email:    {$email}");
        $this->line($generated ? "Password: {$password}" : 'Password: (as entered)');

        return self::SUCCESS;
    }
}
