<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_10_120100_create_import_run_errors_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-049 — CSV-Import: Zeilenfehler je Import-Lauf.
 *
 * Wird sowohl in Preflight (Validierung) als auch in der Ausführung
 * (Persistenz-/Constraint-Fehler) befüllt. Erlaubt späteren Download
 * einer `errors_{run_id}.csv` mit Originalspalten + `_error`.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('import_run_errors', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('import_run_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('row_number');              // 1-basiert ohne Header
            $t->string('field', 64)->nullable();
            $t->string('code', 64);                         // required|format|unique|fkMissing|persist|...
            $t->text('message');                            // i18n-aufgelöst
            $t->json('row_data')->nullable();               // Original-Zeile als assoc array
            $t->timestamp('created_at')->useCurrent();

            $t->index(['import_run_id', 'row_number'], 'import_run_errors_row_idx');
            $t->index(['import_run_id', 'code'], 'import_run_errors_code_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('import_run_errors');
    }
};
