<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Vehicle costs that recur (insurance, road tax, the KTEO inspection) are
 * bills: they fall due on a schedule and want paying. They used to live only
 * as loose vehicle expenses, so the bills list never reminded anyone of them.
 *
 * - Bill categories gain `vehicle_expense`, the vehicle cost they stand for.
 *   Names are free text an admin edits (and in Greek on most installs), so
 *   matching on them would be guesswork; the key is what code relies on.
 * - The four vehicle categories are created, with their providers: the
 *   motorway and bridge operators that charge tolls, the tax authority for
 *   road tax, and whichever insurers the install already has.
 * - Bills gain `vehicle_id`, the car they are for.
 * - Vehicle expenses gain a provider, and the old combined "tax" bucket
 *   becomes road tax, now that road tax and KTEO are told apart.
 */
return new class extends Migration {
    private const CATEGORIES = [
        // Named as the seeder names it, so a fresh install ends up with one
        // insurance category (the seeder finds this one rather than adding
        // its own). Installs that ran this earlier get merged by the next migration.
        'insurance' => ['name' => 'Insurance',         'icon' => 'shield',       'color_hex' => '#14B8A6'],
        'road_tax'  => ['name' => 'Τέλη κυκλοφορίας',  'icon' => 'receipt_long', 'color_hex' => '#0EA5E9'],
        'kteo'      => ['name' => 'ΚΤΕΟ',              'icon' => 'fact_check',   'color_hex' => '#6366F1'],
        'tolls'     => ['name' => 'Διόδια',            'icon' => 'toll',         'color_hex' => '#F59E0B'],
    ];

    private const TOLL_OPERATORS = [
        ['Αττική Οδός', 'https://www.aodos.gr'],
        ['Ολυμπία Οδός', 'https://www.olympiaodos.gr'],
        ['Νέα Οδός', 'https://www.neaodos.gr'],
        ['Κεντρική Οδός', 'https://www.kentrikiodos.gr'],
        ['Μορέας', 'https://www.moreas.com.gr'],
        ['Αυτοκινητόδρομος Αιγαίου', 'https://www.aegeanmotorway.gr'],
        ['Εγνατία Οδός', 'https://www.egnatia.eu'],
        ['Γέφυρα Ρίου-Αντιρρίου', 'https://www.gefyra.gr'],
        ['Υποθαλάσσια Ζεύξη Ακτίου-Πρέβεζας', null],
    ];

    /** Insurers the default seeder ships, for installs whose insurance category was renamed. */
    private const KNOWN_INSURERS = ['Interamerican', 'Ethniki Insurance', 'Allianz', 'AXA', 'Eurolife FFH', 'Generali'];

    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('vehicle_expense', 20)->nullable()->index();
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->foreignUlid('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
        });

        Schema::table('vehicle_expenses', function (Blueprint $table) {
            $table->string('provider_id', 26)->nullable()->index();
        });

        DB::table('vehicle_expenses')->where('category', 'tax')->update(['category' => 'road_tax']);

        $now = now();
        $ids = [];
        foreach (self::CATEGORIES as $key => $attrs) {
            $ids[$key] = DB::table('categories')->where('vehicle_expense', $key)->value('id');
            if (! $ids[$key]) {
                $ids[$key] = (string) Str::ulid();
                DB::table('categories')->insert($attrs + [
                    'id' => $ids[$key], 'vehicle_expense' => $key, 'is_system' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        foreach (self::TOLL_OPERATORS as [$name, $website]) {
            $this->attach($this->provider($name, $website), $ids['tolls']);
        }
        $this->attach($this->provider('ΑΑΔΕ (AADE)', 'https://www.aade.gr'), $ids['road_tax']);

        // Whatever already insures things on this install insures cars too.
        $insurers = DB::table('category_provider')
            ->join('categories', 'categories.id', '=', 'category_provider.category_id')
            ->where(function ($q) {
                foreach (['insurance', 'ασφάλ', 'ασφαλ'] as $word) {
                    $q->orWhereRaw('LOWER(categories.name) LIKE ?', ["%{$word}%"]);
                }
            })
            ->pluck('category_provider.provider_id')
            ->merge(DB::table('providers')->whereIn('name', self::KNOWN_INSURERS)->pluck('id'))
            ->unique();
        foreach ($insurers as $providerId) {
            $this->attach($providerId, $ids['insurance']);
        }
    }

    private function provider(string $name, ?string $website): string
    {
        $id = DB::table('providers')->where('name', $name)->value('id');
        if ($id) {
            return $id;
        }

        $id = (string) Str::ulid();
        DB::table('providers')->insert([
            'id' => $id, 'name' => $name, 'website' => $website,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function attach(string $providerId, string $categoryId): void
    {
        DB::table('category_provider')->insertOrIgnore(['category_id' => $categoryId, 'provider_id' => $providerId]);
    }

    public function down(): void
    {
        DB::table('vehicle_expenses')->whereIn('category', ['road_tax', 'kteo'])->update(['category' => 'tax']);
        DB::table('vehicle_expenses')->where('category', 'tolls')->update(['category' => 'other']);

        Schema::table('vehicle_expenses', function (Blueprint $table) {
            $table->dropIndex(['provider_id']);
            $table->dropColumn('provider_id');
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehicle_id');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['vehicle_expense']);
            $table->dropColumn('vehicle_expense');
        });
    }
};
