<?php

namespace App\Console\Commands;

use App\Models\Family;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateAdmin extends Command
{
    // Renamed command to make:user:admin and keep options for non-interactive usage
    protected $signature = 'make:user:admin {--name= : Admin name} {--email= : Admin email} {--password= : Admin password} {--family-id= : Optional family id}';

    protected $description = 'Create or update an admin user (interactive by default)';

    public function handle(): int
    {
        // Prefer option, otherwise default to 'Admin'
        $name = $this->option('name') ?? 'Admin';

        // Email: prefer option, otherwise prompt the operator until a valid email is given
        $email = $this->option('email') ?? null;
        while (empty($email)) {
            $email = $this->ask('Admin email');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->error('Please enter a valid email address.');
                $email = null;
            }
        }

        // Password: prefer option, otherwise prompt (hidden) and require confirmation
        $password = $this->option('password') ?? null;
        if (empty($password)) {
            do {
                $password = $this->secret('Password');
                $confirm = $this->secret('Confirm Password');

                if ($password !== $confirm) {
                    $this->error('Passwords do not match. Try again.');
                    $password = null; // loop again
                } elseif (empty($password)) {
                    $this->error('Password cannot be empty.');
                    $password = null;
                }
            } while (empty($password));
        }

        $familyId = $this->option('family-id') ?? null;

        // Create or update the user first without assigning family_id to avoid FK errors
        $user = User::firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->password = $password;
        $user->family_role = 'owner';
        // do not set family_id yet

        // mark email verified when possible
        $user->email_verified_at = $user->email_verified_at ?? now();

        $user->save();

        // If a family id was provided, try to attach
        if ($familyId) {
            $family = Family::find($familyId);
            if ($family) {
                $user->family_id = $family->id;
                $user->save();
                $this->info("Attached user to existing family id {$family->id} ({$family->name}).");
            } else {
                // interactive decision: create a new family or skip
                if ($this->confirm("Family ID {$familyId} not found. Create a new family and set you as owner?", true)) {
                    $familyName = $this->ask('Family name', $name . "'s Family");
                    $newFamily = Family::create(['name' => $familyName, 'owner_id' => $user->id]);
                    $user->family_id = $newFamily->id;
                    $user->save();
                    $this->info("Created family {$newFamily->name} (id: {$newFamily->id}) and set user as owner.");
                } else {
                    $this->info('Skipping family assignment.');
                }
            }
        }

        $this->info('Admin created/updated');
        $this->line("Email:    {$email}");
        $this->line("Password: (hidden)");

        return self::SUCCESS;
    }
}

