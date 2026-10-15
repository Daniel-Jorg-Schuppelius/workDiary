<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_21_120000_add_tour_zones_and_stop_links.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 146, MVP-1181/1182: temporäre Zonen aus Tourstopps (Tour, Auftrag,
 * nur am Tourtag gültig; Kunde dann optional) und der Auftrag am
 * Zeitvorschlag des Prüf-Eingangs; Ergebnis je Stopp (erledigt, nicht
 * angetroffen) aus der Fahreransicht (MVP-1183).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('customer_geofences', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->change();
            $table->foreignId('tour_id')->nullable()->after('project_id')->constrained('tours', indexName: 'cgf_tour_fk')->nullOnDelete();
            $table->foreignId('diary_entry_id')->nullable()->after('tour_id')->constrained('diary_entries', indexName: 'cgf_entry_fk')->nullOnDelete();
            $table->date('valid_on')->nullable()->after('diary_entry_id');
        });
        Schema::table('location_pending_entries', function (Blueprint $table): void {
            $table->foreignId('diary_entry_id')->nullable()->after('project_id')->constrained('diary_entries', indexName: 'lpe_entry_fk')->nullOnDelete();
        });
        Schema::table('tour_stops', function (Blueprint $table): void {
            $table->string('outcome', 16)->nullable()->after('actual_source');
        });
    }

    public function down(): void {
        Schema::table('tour_stops', function (Blueprint $table): void {
            $table->dropColumn('outcome');
        });
        Schema::table('location_pending_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('diary_entry_id');
        });
        Schema::table('customer_geofences', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('diary_entry_id');
            $table->dropConstrainedForeignId('tour_id');
            $table->dropColumn('valid_on');
        });
    }
};
