<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One insurance category for everything. The previous migration gave cars a
 * category of their own ("Ασφάλεια οχήματος") next to the general one, which
 * read as a duplicate. The general category now carries the vehicle link
 * (`vehicle_expense = insurance`): its bills offer a vehicle field, and the
 * ones that name a car count towards that car's costs.
 *
 * The car category's bills and providers move to the general one and the car
 * category goes. Where there is no general category, the car one is simply
 * renamed. Fresh installs get one category from the start (the seeder's
 * "Insurance" is the one the earlier migration created).
 */
return new class extends Migration {
    public function up(): void
    {
        $vehicle = DB::table('categories')->where('vehicle_expense', 'insurance')->first();
        if (! $vehicle) {
            return;
        }

        // "σφάλ" matches Ασφάλεια/ασφάλειες without leaning on LOWER(), which
        // SQLite does not apply to Greek.
        $general = DB::table('categories')
            ->where('id', '!=', $vehicle->id)
            ->whereNull('vehicle_expense')
            ->where(fn($q) => $q->where('name', 'like', '%nsurance%')->orWhere('name', 'like', '%σφάλ%')->orWhere('name', 'like', '%σφαλ%'))
            ->orderByDesc('is_system')
            ->orderBy('created_at')
            ->first();

        if (! $general) {
            // Only the name the earlier migration gave it; on a fresh install
            // this is already the seeder's "Insurance" and stays so.
            DB::table('categories')->where('id', $vehicle->id)->where('name', 'Ασφάλεια οχήματος')
                ->update(['name' => 'Ασφάλεια', 'updated_at' => now()]);

            return;
        }

        DB::transaction(function () use ($vehicle, $general) {
            DB::table('bills')->where('category_id', $vehicle->id)->update(['category_id' => $general->id]);

            foreach (DB::table('category_provider')->where('category_id', $vehicle->id)->pluck('provider_id') as $providerId) {
                DB::table('category_provider')->insertOrIgnore(['category_id' => $general->id, 'provider_id' => $providerId]);
            }
            DB::table('category_provider')->where('category_id', $vehicle->id)->delete();

            DB::table('categories')->where('id', $vehicle->id)->delete();
            DB::table('categories')->where('id', $general->id)->update(['vehicle_expense' => 'insurance', 'updated_at' => now()]);
        });
    }

    public function down(): void
    {
        // Which insurance bills were for cars can no longer be told apart
        // from the rest; the merged category stays.
    }
};
