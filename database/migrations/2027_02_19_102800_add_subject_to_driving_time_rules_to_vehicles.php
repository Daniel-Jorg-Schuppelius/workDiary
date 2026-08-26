<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_102800_add_subject_to_driving_time_rules_to_vehicles.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lenk- und Ruhezeiten (Feature 144, MVP-719; Vollscan 2026-08-23, H6):
 * Geltungs-Flag je Fahrzeug — nur Fahrten mit geflaggtem Fahrzeug fließen in
 * die Lenkzeit-Prüfung (VO (EG) 561/2006 / FPersV). Zweiter Schalter ist das
 * Org-Setting compliance.driving_time_rules.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->boolean('subject_to_driving_time_rules')->default(false)->after('logbook_mode');
        });
    }

    public function down(): void {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->dropColumn('subject_to_driving_time_rules');
        });
    }
};
