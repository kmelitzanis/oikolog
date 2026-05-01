<?php

namespace App\Console\Commands;

use App\Models\Family;
use App\Models\User;
use Illuminate\Console\Command;

class CreateUser extends Command
{
    protected $signature = 'make:user {--admin : Create as admin/owner} {--name= : User name} {--email= : User email} {--password= : User password} {--family-id= : Optional family id}';

    protected $description = 'Create or update a user (interactive by default, use --admin for admin/owner)';

    public function handle(): int
    {
        $isAdmin = $this->option('admin');
        $name = $this->option('name') ?? ($isAdmin ? 'Admin' : 'User');
        $email = $this->option('email') ?? null;
        while (empty($email)) {
            $email = $this->ask(($isAdmin ? 'Admin' : 'User') . ' email');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->error('Please enter a valid email address.');
                $email = null;
            }
        }
        $password = $this->option('password') ?? null;
        if (empty($password)) {
            do {
                $password = $this->secret('Password');
                $confirm = $this->secret('Confirm Password');
                if ($password !== $confirm) {
                    $this->error('Passwords do not match. Try again.');
                    $password = null;
                } elseif (empty($password)) {
                    $this->error('Password cannot be empty.');
                    $password = null;
                }
            } while (empty($password));
        }
        $familyId = $this->option('family-id') ?? null;
        $user = User::firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->password = $password;
        $user->family_role = $isAdmin ? 'owner' : 'member';
        $user->is_admin = $isAdmin ? 1 : 0;
        $user->email_verified_at = $user->email_verified_at ?? now();
        $user->save();
        if ($familyId) {
            $family = Family::find($familyId);
            if ($family) {
                $user->family_id = $family->id;
                $user->save();
                $this->info("Attached user to existing family id {$family->id} ({$family->name}).");
            } else if ($isAdmin && $this->confirm("Family ID {$familyId} not found. Create a new family and set you as owner?", true)) {
                $familyName = $this->ask('Family name', $name . "'s Family");
                $newFamily = Family::create(['name' => $familyName, 'owner_id' => $user->id]);
                $user->family_id = $newFamily->id;
                $user->save();
                $this->info("Created family {$newFamily->name} (id: {$newFamily->id}) and set user as owner.");
            } else {
                $this->info('Skipping family assignment.');
            }
        }
        $this->info(($isAdmin ? 'Admin' : 'User') . ' created/updated');
        $this->line("Email:    {$email}");
        $this->line("Password: (hidden)");
        return self::SUCCESS;
    }
}
