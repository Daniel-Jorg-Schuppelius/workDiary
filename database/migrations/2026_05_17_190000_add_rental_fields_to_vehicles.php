<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_17_190000_add_rental_fields_to_vehicles.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds rental/lease metadata to vehicles so the fleet can track
 * temporarily rented cars (with inclusive-kilometer contracts and an
 * excess per-km surcharge).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table): void {
            // owned | leased | rental
            $table->string('ownership', 16)->default('owned')->after('propulsion');
            $table->string('rental_provider', 120)->nullable()->after('ownership');
            $table->date('rental_start')->nullable()->after('rental_provider');
            $table->date('rental_end')->nullable()->after('rental_start');
            $table->decimal('rental_cost_per_day', 8, 2)->nullable()->after('rental_end');
            $table->unsignedInteger('rental_included_km')->nullable()->after('rental_cost_per_day');
            $table->decimal('rental_extra_cost_per_km', 8, 4)->nullable()->after('rental_included_km');

            $table->index(['ownership', 'rental_end']);
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->dropIndex(['ownership', 'rental_end']);
            $table->dropColumn([
                'ownership',
                'rental_provider',
                'rental_start',
                'rental_end',
                'rental_cost_per_day',
                'rental_included_km',
                'rental_extra_cost_per_km',
            ]);
        });
    }
};
