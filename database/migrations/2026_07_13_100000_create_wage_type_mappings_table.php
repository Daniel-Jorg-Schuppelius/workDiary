<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_13_100000_create_wage_type_mappings_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lohnarten-Mapping für den Zeitexport (A21 · MVP-019, ../WorkDiary-Architecture/zeit-export.md §5.1):
 * je Organisation und Export-Profil die Zuordnung interner Lohnarten
 * (work.normal, surcharge.<code>, absence.*, …) auf externe Lohnartennummern
 * des Ziel-Lohnprogramms (DATEV LODAS, Lexware, …).
 *
 * Ohne Mapping bleiben die bisherigen Defaults wirksam (Rückwärts-
 * kompatibilität): wage_type_code der Zuschlagsregel bzw. die
 * `normal_wage_type_code`-Option des Profils für work.normal.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('wage_type_mappings', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->string('profile', 32);          // generic|datev|lexware (config/exports.php)
            $t->string('wage_type', 40);        // interner Schlüssel (TimeExportLine.wage_type)
            $t->string('external_code', 20);    // Lohnartennummer im Zielsystem
            $t->timestamps();

            $t->unique(['organization_id', 'profile', 'wage_type'], 'wtm_org_profile_type_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('wage_type_mappings');
    }
};
