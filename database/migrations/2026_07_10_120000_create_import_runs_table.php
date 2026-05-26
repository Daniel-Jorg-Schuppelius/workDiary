<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_10_120000_create_import_runs_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-049 — CSV-Import: Hauptdatensatz je Import-Lauf.
 *
 * Lebenszyklus eines Laufs:
 *   preflight → awaitingApproval → running → succeeded|partial|failed
 *
 * Pro Entität (customers|projects|users|materials) wird ein Lauf
 * angelegt, der Header-/Format-Validierung, Vorschau, Bestätigung,
 * Ausführung und Fehlerbericht persistiert. Idempotenz erfolgt
 * über `input_hash` + `external_ref` / fachliche Schlüssel.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('import_runs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->string('entity', 32);                       // customers|projects|users|materials
            $t->string('state', 32)->default('preflight');  // preflight|awaitingApproval|running|succeeded|partial|failed
            $t->string('input_filename', 255);
            $t->string('input_hash', 64);                   // SHA-256
            $t->string('storage_path', 255);                // relativer Pfad im Tenant-Storage
            $t->string('delimiter', 4)->default(';');       // ; , \t
            $t->string('encoding', 32)->default('UTF-8');
            $t->unsignedInteger('rows_total')->default(0);
            $t->unsignedInteger('rows_created')->default(0);
            $t->unsignedInteger('rows_updated')->default(0);
            $t->unsignedInteger('rows_skipped')->default(0);
            $t->unsignedInteger('rows_failed')->default(0);
            $t->json('preview')->nullable();                // erste max. 20 Zeilen als Array
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['organization_id', 'entity', 'state'], 'import_runs_org_entity_state_idx');
            $t->index('input_hash', 'import_runs_hash_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('import_runs');
    }
};
