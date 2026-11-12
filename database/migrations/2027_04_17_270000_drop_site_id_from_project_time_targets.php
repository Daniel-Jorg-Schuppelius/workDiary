<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_270000_drop_site_id_from_project_time_targets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1617 (F9-04): Ein Soll je Objekt innerhalb eines Projekts trägt nicht —
 * Zeiteinträge kennen kein Objekt, Befund und Auffüllung gelten je Projekt,
 * Person und Tag. Die Spalte wurde nie geschrieben.
 */
return new class extends Migration {
    public function up(): void {
        if (! Schema::hasColumn('project_time_targets', 'site_id')) {
            return;
        }
        Schema::table('project_time_targets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('site_id');
        });
    }

    public function down(): void {
        if (Schema::hasColumn('project_time_targets', 'site_id')) {
            return;
        }
        Schema::table('project_time_targets', function (Blueprint $table): void {
            $table->foreignId('site_id')->nullable()->after('user_id')->constrained('sites')->nullOnDelete();
        });
    }
};
