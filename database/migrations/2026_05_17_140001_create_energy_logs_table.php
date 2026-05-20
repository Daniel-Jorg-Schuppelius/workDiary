<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_17_140001_create_energy_logs_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unified fuel & charging log (TR1):
 *  - energy_type = fuel   → unit = liter, fuel_kind set, quantity in litres
 *  - energy_type = electric → unit = kwh,  charger meta + SoC values
 *
 * `distance_since_last` is filled by EnergyLogService from the previous
 * entry of the same vehicle (odometer_km diff). Cost-per-unit is computed
 * on the fly in the model accessor.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('energy_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->nullOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // fuel | electric
            $table->string('energy_type', 16)->default('fuel');
            // diesel | petrol | gas | cng | adblue | other
            $table->string('fuel_kind', 16)->nullable();
            // liter | kwh
            $table->string('unit', 8)->default('liter');

            $table->decimal('quantity', 10, 3);
            $table->decimal('cost_total', 10, 2)->nullable();

            $table->unsignedInteger('odometer_km')->nullable();
            $table->unsignedInteger('distance_since_last')->nullable();

            $table->string('location_address')->nullable();
            $table->decimal('location_lat', 10, 7)->nullable();
            $table->decimal('location_lng', 10, 7)->nullable();

            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('duration_minutes')->default(0);

            // Charging only: 0..100 percent
            $table->unsignedTinyInteger('soc_before')->nullable();
            $table->unsignedTinyInteger('soc_after')->nullable();
            // level1 | level2 | dc_fast | other
            $table->string('charger_type', 16)->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['vehicle_id', 'started_at']);
            $table->index(['user_id', 'started_at']);
            $table->index(['organization_id', 'started_at']);
            $table->index('energy_type');
        });
    }

    public function down(): void {
        Schema::dropIfExists('energy_logs');
    }
};
