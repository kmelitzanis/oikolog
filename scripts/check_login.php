<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$email = $argv[1] ?? 'kmelitzanis@outlook.com';
$pw = $argv[2] ?? 'Lightzeus23';
$user = App\Models\User::where('email', $email)->first();
if (!$user) {
    echo "NO_USER\n";
    exit(0);
}

echo "User ID: {$user->id}\n";
echo "Stored password hash: {$user->password}\n";
$ok = Illuminate\Support\Facades\Hash::check($pw, $user->password) ? 'OK' : 'FAIL';
echo "Password check for '{$pw}': {$ok}\n";
// Simulate attempt via Auth guard
$attempt = Illuminate\Support\Facades\Auth::attempt(['email' => $email, 'password' => $pw]);
echo "Auth::attempt result: " . ($attempt ? 'OK' : 'FAIL') . "\n";

