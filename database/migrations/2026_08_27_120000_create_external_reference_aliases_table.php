<?php
/*
 * Created on   : Tue Jun 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_27_120000_create_external_reference_aliases_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alias-/Weiterleitungstabelle für Fremd-IDs, die beim Zusammenführen von
 * Dubletten (Projekt-/Kunden-Merge) auf ein anderes Ziel zeigen sollen.
 *
 * Hintergrund: external_references erlaubt über den Unique-Index
 * (plugin_id, external_type, referenceable_type, referenceable_id) nur EINE
 * Primär-Referenz je Plugin/Typ und lokalem Datensatz. Hatten Quelle und Ziel
 * eines Merges je eine eigene Fremd-ID (z. B. zwei unterschiedliche
 * Toggl-Projektnamen), würde die Quell-Referenz verworfen — künftige Importe
 * mit dem alten Schlüssel landen dann in der Inbox. Diese Tabelle bewahrt die
 * alte Fremd-ID als zusätzlichen Verweis aufs Ziel, sodass der Import sie ohne
 * Inbox-Umweg direkt auflöst (siehe ProjectMergeService/CustomerMergeService).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('external_reference_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('plugin_id', 64);
            $table->string('external_type', 64);
            $table->string('external_id');            // alte/zusätzliche Fremd-ID
            $table->morphs('referenceable');          // heutiges Ziel-Modell (nach Merge)
            $table->timestamps();

            // Eine Fremd-ID darf je Org/Plugin/Typ nur auf EIN Ziel zeigen.
            $table->unique(['organization_id', 'plugin_id', 'external_type', 'external_id'], 'extref_alias_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('external_reference_aliases');
    }
};
