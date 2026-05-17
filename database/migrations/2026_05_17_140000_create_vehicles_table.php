<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_17_140000_create_vehicles_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fleet vehicles (TR1):
 *  - Organisation-wide pool, optional default driver.
 *  - Holds propulsion + capacity metadata so EnergyLog/TravelLog can
 *    compute consumption and reimbursement rates.
 *  - Soft archive via `archived_at` (no soft delete column, matches the
 *    pattern already used by Holiday / OnCallShift).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->nullOnDelete();
            $table->string('license_plate', 32);
            $table->string('label', 120)->nullable();

            // car | van | truck | bicycle | other
            $table->string('vehicle_type', 32)->default('car');
            // diesel | petrol | gas | hybrid | electric | muscle | other
            $table->string('propulsion', 32)->default('petrol');

            $table->foreignId('default_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->decimal('default_rate_per_km', 8, 4)->nullable();

            $table->decimal('tank_capacity_liters', 8, 2)->nullable();
            $table->decimal('battery_capacity_kwh', 8, 2)->nullable();
            $table->decimal('wltp_consumption', 8, 3)->nullable();
            $table->unsignedInteger('odometer_km')->nullable();

            $table->text('notes')->nullable();
            $table->timestamp('archived_at')->nullable();

            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['organization_id', 'archived_at']);
            $table->index('default_user_id');
            $table->index('license_plate');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
