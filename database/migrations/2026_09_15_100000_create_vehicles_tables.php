<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vehicles: the things a household spends money on that are not bills.
 *
 * A car costs money on two different clocks at once — the calendar (insurance,
 * road tax, MOT) and the odometer (service, oil, tyres) — which is why a
 * reminder here carries both a date and a mileage and is due on whichever
 * arrives first. Bills cannot express that, so vehicles get their own tables
 * rather than being bent into the bill schedule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            // 'car' | 'motorcycle' | 'other' — drives the icon and nothing else,
            // so an unknown value degrades to a generic vehicle rather than breaking.
            $table->string('type', 20)->default('car');
            $table->string('brand', 80)->nullable();
            $table->string('model', 80)->nullable();
            $table->string('plate', 20)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            // Free text ("Hybrid", "scooter"): the design shows it beside the
            // year as a label, and enumerating fuel types buys nothing here.
            $table->string('variant', 40)->nullable();

            // The odometer as last known, with the date it was read. Cost per km
            // is meaningless without knowing when the reading was taken.
            $table->unsignedInteger('odometer_km')->default(0);
            $table->date('odometer_read_at')->nullable();
            $table->date('purchase_date')->nullable();
            $table->unsignedInteger('purchase_km')->nullable();

            $table->string('insurer', 80)->nullable();
            $table->date('insurance_due')->nullable();

            // One photo per vehicle, stored like a recipe's: a path on the
            // public disk rather than a media-library collection, because there
            // is exactly one and it is replaced, never accumulated.
            $table->string('photo_path')->nullable();

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_shared')->default(false);

            $table->foreignUlid('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('family_id')->nullable()->constrained('families')->nullOnDelete();
            $table->timestamps();

            $table->index(['created_by', 'is_active']);
        });

        Schema::create('vehicle_reminders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->string('label');

            // Due on whichever comes first. Both are nullable: an MOT is a pure
            // date, a tyre change is pure mileage, an oil change is both.
            $table->date('due_date')->nullable();
            $table->unsignedInteger('due_km')->nullable();

            // How to regenerate the next one after this is ticked off.
            $table->unsignedSmallInteger('interval_months')->nullable();
            $table->unsignedInteger('interval_km')->nullable();

            $table->string('note')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'completed_at']);
        });

        Schema::create('vehicle_services', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->date('performed_at');
            $table->unsignedInteger('odometer_km')->nullable();
            $table->string('shop', 120)->nullable();
            $table->decimal('total', 10, 2)->default(0);
            // Line items ({what, cost}); a workshop receipt is a flat list read
            // as a whole, never queried per line, so a table would only add joins.
            $table->json('lines')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'performed_at']);
        });

        Schema::create('vehicle_expenses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            // 'fuel' | 'service' | 'insurance' | 'tax' | 'other'
            $table->string('category', 20)->default('other');
            $table->decimal('amount', 10, 2);
            $table->date('spent_at');
            $table->unsignedInteger('odometer_km')->nullable();
            // Fuel only; lets litres/100km follow later without another table.
            $table->decimal('litres', 8, 2)->nullable();
            $table->string('note')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vehicle_id', 'category', 'spent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_expenses');
        Schema::dropIfExists('vehicle_services');
        Schema::dropIfExists('vehicle_reminders');
        Schema::dropIfExists('vehicles');
    }
};
