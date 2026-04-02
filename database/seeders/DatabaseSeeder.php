<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Provider;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Default Categories ────────────────────────────────────────────────
        $categories = [
            ['name' => 'Loan',          'icon' => 'account_balance',       'color_hex' => '#6366F1'],
            ['name' => 'Credit Card', 'icon' => 'credit_card', 'color_hex' => '#8B5CF6'],
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

        // ── Default Providers ─────────────────────────────────────────────────
        // Each entry lists ALL categories the provider belongs to.
        $providers = [
            // Banks — appear in both Loan and Credit Card
            ['name' => 'Alpha Bank', 'categories' => ['Loan', 'Credit Card'], 'website' => 'https://www.alpha.gr', 'phone' => '210 326 0000'],
            ['name' => 'Eurobank', 'categories' => ['Loan', 'Credit Card'], 'website' => 'https://www.eurobank.gr', 'phone' => '210 955 5000'],
            ['name' => 'Piraeus Bank', 'categories' => ['Loan', 'Credit Card'], 'website' => 'https://www.piraeusbank.gr', 'phone' => '210 328 8000'],
            ['name' => 'National Bank of Greece', 'categories' => ['Loan', 'Credit Card'], 'website' => 'https://www.nbg.gr', 'phone' => '210 484 8484'],
            ['name' => 'Attica Bank', 'categories' => ['Loan', 'Credit Card'], 'website' => 'https://www.atticabank.gr', 'phone' => '210 366 9000'],

            // Credit card only
            ['name' => 'American Express', 'categories' => ['Credit Card'], 'website' => 'https://www.americanexpress.com', 'phone' => null],
            ['name' => 'Revolut', 'categories' => ['Credit Card'], 'website' => 'https://www.revolut.com', 'phone' => null],

            // Electricity
            ['name' => 'ΔΕΗ (PPC)', 'categories' => ['Electricity'], 'website' => 'https://www.dei.gr', 'phone' => '11770'],
            ['name' => 'Heron', 'categories' => ['Electricity'], 'website' => 'https://www.heron.gr', 'phone' => '1810'],
            ['name' => 'Zenith Energy', 'categories' => ['Electricity'], 'website' => 'https://www.zenithenergy.gr', 'phone' => '1800'],
            ['name' => 'NRG', 'categories' => ['Electricity'], 'website' => 'https://www.nrg.gr', 'phone' => '1500'],
            ['name' => 'Protergia', 'categories' => ['Electricity', 'Gas'], 'website' => 'https://www.protergia.gr', 'phone' => '1822'],
            ['name' => 'Elpedison', 'categories' => ['Electricity'], 'website' => 'https://www.elpedison.gr', 'phone' => '1855'],

            // Water
            ['name' => 'ΕΥΔΑΠ (EYDAP)', 'categories' => ['Water'], 'website' => 'https://www.eydap.gr', 'phone' => '210 300 0100'],
            ['name' => 'ΕΥΑΘ (EYATH)', 'categories' => ['Water'], 'website' => 'https://www.eyath.gr', 'phone' => '2310 966 100'],

            // Gas
            ['name' => 'ΕΔΑ Αττικής', 'categories' => ['Gas'], 'website' => 'https://www.edaattikis.gr', 'phone' => '210 901 3000'],
            ['name' => 'ΕΔΑ ΘΕΣΣ', 'categories' => ['Gas'], 'website' => 'https://www.edathess.gr', 'phone' => '2310 596 000'],

            // Telecom — Internet & Phone
            ['name' => 'Cosmote', 'categories' => ['Internet', 'Phone'], 'website' => 'https://www.cosmote.gr', 'phone' => '13888'],
            ['name' => 'Vodafone', 'categories' => ['Internet', 'Phone'], 'website' => 'https://www.vodafone.gr', 'phone' => '1305'],
            ['name' => 'Nova', 'categories' => ['Internet', 'Phone'], 'website' => 'https://www.nova.gr', 'phone' => '13831'],
            ['name' => 'Forthnet', 'categories' => ['Internet'], 'website' => 'https://www.forthnet.gr', 'phone' => '13838'],
            ['name' => 'Cyta', 'categories' => ['Internet', 'Phone'], 'website' => 'https://www.cyta.gr', 'phone' => '13838'],

            // Streaming
            ['name' => 'Netflix', 'categories' => ['Streaming'], 'website' => 'https://www.netflix.com', 'phone' => null],
            ['name' => 'Disney+', 'categories' => ['Streaming'], 'website' => 'https://www.disneyplus.com', 'phone' => null],
            ['name' => 'Apple TV+', 'categories' => ['Streaming'], 'website' => 'https://tv.apple.com', 'phone' => null],
            ['name' => 'YouTube Premium', 'categories' => ['Streaming'], 'website' => 'https://www.youtube.com/premium', 'phone' => null],
            ['name' => 'Max (HBO)', 'categories' => ['Streaming'], 'website' => 'https://www.max.com', 'phone' => null],
            ['name' => 'Amazon Prime', 'categories' => ['Streaming'], 'website' => 'https://www.primevideo.com', 'phone' => null],

            // Entertainment / Music / Gaming
            ['name' => 'Spotify', 'categories' => ['Entertainment'], 'website' => 'https://www.spotify.com', 'phone' => null],
            ['name' => 'Apple Music', 'categories' => ['Entertainment'], 'website' => 'https://music.apple.com', 'phone' => null],
            ['name' => 'YouTube Music', 'categories' => ['Entertainment'], 'website' => 'https://music.youtube.com', 'phone' => null],
            ['name' => 'Xbox Game Pass', 'categories' => ['Entertainment'], 'website' => 'https://www.xbox.com/gamepass', 'phone' => null],
            ['name' => 'PlayStation+', 'categories' => ['Entertainment'], 'website' => 'https://www.playstation.com', 'phone' => null],
            ['name' => 'Steam', 'categories' => ['Entertainment'], 'website' => 'https://store.steampowered.com', 'phone' => null],

            // Insurance
            ['name' => 'Interamerican', 'categories' => ['Insurance'], 'website' => 'https://www.interamerican.gr', 'phone' => '210 900 5000'],
            ['name' => 'Ethniki Insurance', 'categories' => ['Insurance'], 'website' => 'https://www.ethniki-asfalistiki.gr', 'phone' => '210 909 0000'],
            ['name' => 'Allianz', 'categories' => ['Insurance'], 'website' => 'https://www.allianz.gr', 'phone' => '210 693 1000'],
            ['name' => 'AXA', 'categories' => ['Insurance'], 'website' => 'https://www.axa.gr', 'phone' => '210 726 8100'],
            ['name' => 'Eurolife FFH', 'categories' => ['Insurance'], 'website' => 'https://www.eurolife.gr', 'phone' => '210 930 8000'],
            ['name' => 'Generali', 'categories' => ['Insurance'], 'website' => 'https://www.generali.gr', 'phone' => '210 809 4000'],

            // Groceries
            ['name' => 'AB Vassilopoulos', 'categories' => ['Groceries'], 'website' => 'https://www.ab.gr', 'phone' => null],
            ['name' => 'Sklavenitis', 'categories' => ['Groceries'], 'website' => 'https://www.sklavenitis.gr', 'phone' => null],
            ['name' => 'Lidl', 'categories' => ['Groceries'], 'website' => 'https://www.lidl.gr', 'phone' => null],
            ['name' => 'My Market', 'categories' => ['Groceries'], 'website' => 'https://www.mymarket.gr', 'phone' => null],
            ['name' => 'Masoutis', 'categories' => ['Groceries'], 'website' => 'https://www.masoutis.gr', 'phone' => null],

            // Transport
            ['name' => 'ΟΑΣΑ (OASA)', 'categories' => ['Transport'], 'website' => 'https://www.oasa.gr', 'phone' => '185'],
            ['name' => 'ΟΣΕ (Hellenic Rail)', 'categories' => ['Transport'], 'website' => 'https://www.trainose.gr', 'phone' => '14511'],
            ['name' => 'Aegean Airlines', 'categories' => ['Transport'], 'website' => 'https://www.aegeanair.com', 'phone' => '210 626 1000'],
            ['name' => 'Ryanair', 'categories' => ['Transport'], 'website' => 'https://www.ryanair.com', 'phone' => null],

            // Gym
            ['name' => 'Holmes Place', 'categories' => ['Gym'], 'website' => 'https://www.holmesplace.com', 'phone' => null],
            ['name' => 'McFit', 'categories' => ['Gym'], 'website' => 'https://www.mcfit.com/gr', 'phone' => null],
            ['name' => 'Anytime Fitness', 'categories' => ['Gym'], 'website' => 'https://www.anytimefitness.com', 'phone' => null],

            // Education
            ['name' => 'Coursera', 'categories' => ['Education'], 'website' => 'https://www.coursera.org', 'phone' => null],
            ['name' => 'Udemy', 'categories' => ['Education'], 'website' => 'https://www.udemy.com', 'phone' => null],
            ['name' => 'LinkedIn Learning', 'categories' => ['Education'], 'website' => 'https://www.linkedin.com/learning', 'phone' => null],
        ];

        // Pre-load categories into a lookup map for efficiency
        $categoryMap = Category::whereIn('name', collect($providers)->pluck('categories')->flatten()->unique()->values()->all())
            ->get()
            ->keyBy('name');

        foreach ($providers as $item) {
            $provider = Provider::firstOrCreate(
                ['name' => $item['name']],
                ['website' => $item['website'] ?? null, 'phone' => $item['phone'] ?? null]
            );

            $categoryIds = collect($item['categories'])
                ->map(fn($name) => $categoryMap->get($name)?->id)
                ->filter()
                ->values()
                ->all();

            if ($categoryIds) {
                $provider->categories()->syncWithoutDetaching($categoryIds);
            }
        }
    }
}
