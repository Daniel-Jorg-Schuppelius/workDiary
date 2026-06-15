<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_14_210100_create_vehicle_reservations_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 028 — Fahrzeug-Reservierung für die Disposition.
 *
 * Reserviert ein Fahrzeug für ein Zeitfenster, optional an einen Auftrag
 * (diary_entry) gebunden. Doppelreservierungen im selben Zeitfenster werden
 * vom VehicleReservationService über eine Overlap-Prüfung verhindert.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('vehicle_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('diary_entry_id')->nullable()->constrained('diary_entries')->nullOnDelete();
            $table->foreignId('reserved_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('reserved_from');
            $table->timestamp('reserved_to');
            $table->string('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'vehicle_id'], 'veh_res_org_vehicle_idx');
            $table->index(['vehicle_id', 'reserved_from', 'reserved_to'], 'veh_res_window_idx');
            $table->index('diary_entry_id', 'veh_res_diary_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('vehicle_reservations');
    }
};
