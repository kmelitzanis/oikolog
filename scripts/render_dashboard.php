<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$email = $argv[1] ?? 'admin2@example.com';
$user = App\Models\User::where('email', $email)->first();
if (!$user) {
    echo "NO_USER\n";
    exit(0);
}

Illuminate\Support\Facades\Auth::login($user);

try {
    echo view('dashboard.index', [
        'user' => $user,
        'stats' => (new App\Http\Controllers\Web\DashboardController)->index() ?? [],
    ])->render();
} catch (Throwable $e) {
    echo "EXCEPTION: " . get_class($e) . " - " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

