<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_100500_create_import_value_mappings_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tag-/Kategorie-Mapping für den CSV-Import (Feature 024, Rang 58):
 * persistente Zuordnung unbekannter Quellwerte je Organisation + Entität
 * (Muster ExternalReferenceAlias) — Wiederholimporte nutzen die einmal
 * getroffene Entscheidung, keine Blind-Neuanlage.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('import_value_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // Import-Entität (z. B. customers) — Quelle der Werte.
            $table->string('entity', 32);
            // Quellwert normalisiert (lowercase, getrimmt).
            $table->string('source_value', 191);
            // Ziel: 'tag' (tag_id gesetzt) oder 'ignore'.
            $table->string('target_kind', 16);
            $table->foreignId('tag_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'entity', 'source_value'], 'ivm_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('import_value_mappings');
    }
};
