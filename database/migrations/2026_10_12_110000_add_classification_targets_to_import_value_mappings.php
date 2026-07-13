<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_12_110000_add_classification_targets_to_import_value_mappings.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A13 (MVP-049): Klassifikations-Ziele im Import-Wertmapping.
 *
 * 1. `import_value_mappings.classification_id` — ein Quellwert kann neben
 *    Tag/Ignorieren auch auf eine Klassifikation (Katalog mit Org-Override)
 *    gemappt werden (`target_kind` = 'classification').
 * 2. `classifiables` — polymorphe Zuordnung Klassifikation ↔ Zielobjekt
 *    (Muster `taggables`); der Import hängt gemappte Klassifikationen damit
 *    idempotent an die importierten Datensätze.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('import_value_mappings', function (Blueprint $table): void {
            // Ziel-Klassifikation (nullable — nur bei target_kind 'classification').
            $table->foreignId('classification_id')->nullable()->after('tag_id')
                ->constrained('classifications')->cascadeOnDelete();
        });

        Schema::create('classifiables', function (Blueprint $table): void {
            $table->foreignId('classification_id')->constrained()->cascadeOnDelete();
            $table->morphs('classifiable');
            $table->primary(['classification_id', 'classifiable_id', 'classifiable_type'], 'classifiables_pk');
        });
    }

    public function down(): void {
        Schema::dropIfExists('classifiables');
        Schema::table('import_value_mappings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('classification_id');
        });
    }
};
