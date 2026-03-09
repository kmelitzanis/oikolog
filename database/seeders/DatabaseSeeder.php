<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Default Categories ────────────────────────────────────────────────
        $categories = [
            ['name' => 'Loan',          'icon' => 'account_balance',       'color_hex' => '#6366F1'],
            ['name' => 'Electricity',   'icon' => 'bolt',                  'color_hex' => '#F59E0B'],
            ['name' => 'Water',         'icon' => 'water_drop',            'color_hex' => '#3B82F6'],
            ['name' => 'Gas',           'icon' => 'local_fire_department', 'color_hex' => '#EF4444'],
            ['name' => 'Entertainment', 'icon' => 'movie',                 'color_hex' => '#EC4899'],
            ['name' => 'Internet',      'icon' => 'wifi',                  'color_hex' => '#8B5CF6'],
            ['name' => 'Insurance',     'icon' => 'shield',                'color_hex' => '#14B8A6'],
            ['name' => 'Rent',          'icon' => 'home',                  'color_hex' => '#DC2626'],
            ['name' => 'Phone',         'icon' => 'smartphone',            'color_hex' => '#059669'],
            ['name' => 'Groceries',     'icon' => 'shopping_cart',         'color_hex' => '#D97706'],
            ['name' => 'Transport',     'icon' => 'directions_car',        'color_hex' => '#0EA5E9'],
            ['name' => 'Streaming',     'icon' => 'play_circle',           'color_hex' => '#7C3AED'],
            ['name' => 'Gym',           'icon' => 'fitness_center',        'color_hex' => '#10B981'],
            ['name' => 'Education',     'icon' => 'school',                'color_hex' => '#F97316'],
            ['name' => 'Other',         'icon' => 'category',              'color_hex' => '#6B7280'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(
                ['name' => $cat['name'], 'is_system' => true],
                ['icon' => $cat['icon'], 'color_hex' => $cat['color_hex'], 'is_system' => true]
            );
        }

        // Note: Admin user creation moved to `make:user:admin` artisan command.
    }
}
