<?php

/*
 * Created on   : Tue May 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_19_100002_add_maintenance_flag_to_projects.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            // Sammel-/Wartungsprojekt: dauerhaft offen, nimmt regelmäßig Aufträge
            // auf (z.B. DATEV-Updates, Auto-Wartung, "Vorbeischauen bei …").
            $table->boolean('is_maintenance')->default(false)->after('is_default');

            // Default-Standort für neue Aufträge dieses Projekts (onsite | remote
            // | hybrid). Bleibt null, wenn pro Auftrag entschieden werden soll.
            $table->string('default_location_mode', 16)->nullable()->after('is_maintenance');

            $table->index('is_maintenance', 'projects_is_maintenance_idx');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropIndex('projects_is_maintenance_idx');
            $table->dropColumn(['is_maintenance', 'default_location_mode']);
        });
    }
};
