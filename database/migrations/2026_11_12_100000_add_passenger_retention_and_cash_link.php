<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_11_12_100000_add_passenger_retention_and_cash_link.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-456-Lückenschluss (Issue #74):
 *
 *  - `passenger_rides.anonymized_at`: Marker der Retention-Anonymisierung —
 *    Orts-/Fahrgastfelder werden nach Frist genullt, die Fahrt selbst bleibt
 *    als kaufmännischer/steuerlicher Nachweis (Beträge, Steuer, Zeiten).
 *  - `passenger_shift_settlements.cash_entry_id`: nachvollziehbare Übergabe
 *    des Barumsatzes einer abgeschlossenen Schichtabrechnung ins Kassenbuch
 *    (MVP-414); genau eine Buchung je Abrechnung, rückverlinkt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('passenger_rides', function (Blueprint $table): void {
            $table->timestamp('anonymized_at')->nullable()->after('closing_note');
        });
        Schema::table('passenger_shift_settlements', function (Blueprint $table): void {
            $table->foreignId('cash_entry_id')->nullable()->after('closed_at')
                ->constrained('cash_entries', indexName: 'pss_cash_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('passenger_shift_settlements', function (Blueprint $table): void {
            $table->dropForeign('pss_cash_fk');
            $table->dropColumn('cash_entry_id');
        });
        Schema::table('passenger_rides', function (Blueprint $table): void {
            $table->dropColumn('anonymized_at');
        });
    }
};
