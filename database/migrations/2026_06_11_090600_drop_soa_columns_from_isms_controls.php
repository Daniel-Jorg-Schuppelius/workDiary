<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_11_090600_drop_soa_columns_from_isms_controls.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abschluss der Datenmigration 2026_06_11_090500: isms_controls wird zur
 * NORMNEUTRALEN Maßnahme (Feature 046). Die Normreferenz (code/source)
 * lebt jetzt in isms_requirements, die SoA-Aussage (applicable/
 * justification) in isms_applicability_statements. Alte Unique-/Indizes
 * auf den entfallenden Spalten werden VOR dem Spalten-Drop entfernt
 * (SQLite-kompatibel: Laravel 11 nativer Column-Drop).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('isms_controls', function (Blueprint $table): void {
            if (Schema::hasIndex('isms_controls', 'isms_controls_org_code_uq')) {
                $table->dropUnique('isms_controls_org_code_uq');
            }
            if (Schema::hasIndex('isms_controls', 'isms_controls_org_source_idx')) {
                $table->dropIndex('isms_controls_org_source_idx');
            }
        });

        Schema::table('isms_controls', function (Blueprint $table): void {
            $columns = array_values(array_filter(
                ['code', 'source', 'applicable', 'justification'],
                static fn(string $column): bool => Schema::hasColumn('isms_controls', $column),
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    public function down(): void {
        // Struktur-Rollback (Daten sind nicht wiederherstellbar — Backup nutzen).
        Schema::table('isms_controls', function (Blueprint $table): void {
            $table->string('code', 24)->nullable();
            $table->string('source', 24)->default('custom');
            $table->boolean('applicable')->default(true);
            $table->text('justification')->nullable();
        });
    }
};
