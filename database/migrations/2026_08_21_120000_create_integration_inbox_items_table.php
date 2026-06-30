<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_21_120000_create_integration_inbox_items_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Universelle Zuordnungs-Inbox für alle Datenimporte (CSV-Wizard + Plugin-Syncs).
 * Ersetzt mittelfristig die plugin-spezifischen Staging-Tabellen
 * (toggl_pending_entries, openproject_pending_entries, remote_pending_sessions)
 * sowie pending_external_conflicts.
 *
 * Drei Fall-Typen (case_type):
 *  - unmatched: eingegangen, noch keinem lokalen Datensatz zugeordnet
 *  - conflict:  zugeordnet, aber Feldwerte weichen ab
 *  - ambiguous: mehrere lokale Kandidaten, Mensch muss wählen
 *
 * Bewusst polymorphe Ziel-Spalten OHNE FK (mehrere Ziel-Entitäten möglich);
 * referentielle Integrität über IntegrationResolver + Tests.
 *
 * @see docs/features/053-datenimport-integrations-drehscheibe.md
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('integration_inbox_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('plugin_id', 64);            // toggl | lexoffice | openproject | remote-support | csv-import
            $table->string('source', 16)->nullable();   // api | csv | manual
            $table->string('target_type', 191);         // Ziel-Morph: App\Models\Customer | Supplier | Project | TimeEntry ...
            $table->string('external_type', 64);        // client | project | entry | contact | session ...
            $table->string('external_id', 191)->nullable();
            $table->string('dedupe_key', 191);          // plugin:external_type:external_id | hash(csv-row)
            $table->string('case_type', 16);            // unmatched | conflict | ambiguous
            $table->string('status', 24)->default('open'); // open | resolved_linked | resolved_created |
                                                            // resolved_local | resolved_remote | dismissed

            // (Haupt-)Kandidat bzw. Ergebnis — polymorph ohne FK (Multi-Target).
            $table->string('referenceable_type', 191)->nullable();
            $table->unsignedBigInteger('referenceable_id')->nullable();
            $table->json('candidate_ids')->nullable();        // [{id, score, reasons[]}]
            $table->string('resolved_to_type', 191)->nullable();
            $table->unsignedBigInteger('resolved_to_id')->nullable();

            // Daten
            $table->json('remote_snapshot');             // Roh-Payload der Quelle (Anzeige/Audit)
            $table->json('mapped_snapshot')->nullable();  // ins lokale Schema gemappt (Anlegen/Konflikt-Übernahme)
            $table->json('local_snapshot')->nullable();   // conflict: aktuelle lokale Werte
            $table->json('diff_fields')->nullable();

            // Denormalisierte Anzeige + Gruppierung
            $table->string('display_title')->nullable();
            $table->string('display_subtitle')->nullable();
            $table->timestamp('occurred_at')->nullable();

            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'plugin_id', 'dedupe_key'], 'iii_dedupe_unique');
            $table->index(['organization_id', 'status', 'target_type'], 'iii_status_target_idx');
            $table->index(['plugin_id', 'external_type', 'external_id'], 'iii_external_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('integration_inbox_items');
    }
};
