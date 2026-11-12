<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_230000_add_condition_and_checklist_to_rental_profiles.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Verleihprofil (MVP-1604): aktueller Zustand des Leihobjekts als Gegenstück
 * zum Mindestzustand und die Prüfpunkte der Protokoll-Checkliste.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('rental_profiles', function (Blueprint $table): void {
            $table->string('current_condition', 20)->nullable()->after('min_condition');
            $table->json('checklist_labels')->nullable()->after('accessories');
        });

        // `min_condition` war Freitext und wird jetzt auf RentalCondition gecastet; Unbekanntes würfe beim Lesen.
        DB::table('rental_profiles')
            ->whereNotNull('min_condition')
            ->whereNotIn('min_condition', ['new', 'good', 'used', 'worn', 'damaged'])
            ->update(['min_condition' => null]);
    }

    public function down(): void {
        Schema::table('rental_profiles', function (Blueprint $table): void {
            $table->dropColumn(['current_condition', 'checklist_labels']);
        });
    }
};
