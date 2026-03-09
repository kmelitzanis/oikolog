<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;

class DashboardController extends Controller
{
    public function index()
    {
        $user  = Auth::user();
        $bills = Bill::forUser($user)->active()->with('category')->orderBy('next_due_date')->get();

        $stats = [
            'monthly_total' => round($bills->sum(fn($b) => $b->monthlyEquivalent()), 2),
            'yearly_total'  => round($bills->sum(fn($b) => $b->monthlyEquivalent()) * 12, 2),
            'active_count'  => $bills->count(),
            'overdue_count' => $bills->filter(fn($b) => $b->isOverdue())->count(),
            'due_this_week' => $bills->filter(fn($b) => $b->daysUntilDue() >= 0 && $b->daysUntilDue() <= 7)->count(),
        ];

        $upcoming   = Bill::forUser($user)->dueWithin(30)->with('category')->orderBy('next_due_date')->take(8)->get();
        $byCategory = $bills->groupBy('category.name')
            ->map(fn($g) => round($g->sum(fn($b) => $b->monthlyEquivalent()), 2))
            ->sortDesc();

        return view('dashboard.index', compact('user', 'stats', 'upcoming', 'byCategory'));
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended('/');
        }

        return back()->withErrors(['email' => 'Invalid credentials.'])->withInput();
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'email'         => ['required', 'email', 'unique:users'],
            'password'      => ['required', 'confirmed', 'min:8'],
            'currency_code' => ['nullable', 'string', 'size:3'],
        ]);

        $user = User::create([
            'name'          => $data['name'],
            'email'         => $data['email'],
            'password'      => $data['password'],
            'currency_code' => $data['currency_code'] ?? 'EUR',
        ]);

        Auth::login($user);
        return redirect('/');
    }

    // Show settings form
    public function settings()
    {
        $user = Auth::user();
        return view('settings.index', compact('user'));
    }

    // Update settings
    public function updateSettings(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email,' . $user->id],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'password' => ['nullable', 'confirmed', 'min:8'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'avatar_url' => ['nullable', 'url'],
            'locale' => ['nullable', 'string'],
        ]);

        $update = [
            'name' => $data['name'],
            'email' => $data['email'],
            'currency_code' => $data['currency_code'] ?? $user->currency_code,
            'avatar_url' => $data['avatar_url'] ?? $user->avatar_url,
            'locale' => $data['locale'] ?? $user->locale ?? 'en',
        ];

        if (!empty($data['password'])) {
            $update['password'] = $data['password'];
        }

        // Handle uploaded avatar image
        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            // If Spatie medialibrary is installed and model uses it, attach via medialibrary
            if (method_exists($user, 'addMedia')) {
                // attach single avatar (clear previous if singleFile not used)
                try {
                    $user->clearMediaCollection('avatars');
                } catch (\Throwable $e) {
                    // ignore if not supported
                }
                try {
                    $user->addMedia($file->getRealPath())->usingFileName(uniqid() . '.' . $file->getClientOriginalExtension())->toMediaCollection('avatars');
                    $update['avatar_url'] = $user->getFirstMediaUrl('avatars', 'thumb') ?: $update['avatar_url'];
                } catch (\Throwable $e) {
                    // fallthrough to fallback storage
                }
            }

            // If avatar_url still not set, fallback to Intervention resize + store
            if (empty($update['avatar_url'])) {
                if (class_exists(\Intervention\Image\ImageManagerStatic::class)) {
                    $img = Image::make($file->getRealPath())->fit(256, 256, function ($constraint) {
                        $constraint->upsize();
                    })->encode('jpg', 85);
                    $filename = 'avatars/' . uniqid() . '.jpg';
                    Storage::disk('public')->put($filename, (string)$img);
                    $update['avatar_url'] = Storage::url($filename);
                } else {
                    // Fallback: store original file if imagemagick/gd not available
                    $path = $file->store('avatars', 'public');
                    $update['avatar_url'] = Storage::url($path);
                }
            }
        }

        $user->update($update);

        return back()->with('success', 'Settings updated.');
    }

    // Set locale via quick route (session + user if authenticated)
    public function setLocale($lang)
    {
        $locales = $this->availableLocales();
        if (!in_array($lang, $locales)) abort(400);
        session(['locale' => $lang]);
        \Illuminate\Support\Facades\App::setLocale($lang);
        if ($user = Auth::user()) {
            $user->update(['locale' => $lang]);
        }
        // Redirect back explicitly so a fresh request picks up the session locale
        $back = url()->previous() ?: route('dashboard');
        return redirect()->to($back)->with('success', __('Settings updated.'));
    }

    private function availableLocales(): array
    {
        $langPath = resource_path('lang');
        $locales = [];
        if (is_dir($langPath)) {
            foreach (scandir($langPath) as $entry) {
                if (in_array($entry, ['.', '..'])) continue;
                $full = $langPath . DIRECTORY_SEPARATOR . $entry;
                if (is_file($full) && pathinfo($full, PATHINFO_EXTENSION) === 'json') {
                    $locales[] = pathinfo($full, PATHINFO_FILENAME);
                } elseif (is_dir($full)) {
                    $locales[] = $entry;
                }
            }
        }
        return array_values(array_unique($locales));
    }
}
