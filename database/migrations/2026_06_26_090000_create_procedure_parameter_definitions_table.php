<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_26_090000_create_procedure_parameter_definitions_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versionierte, typisierte Auftragsparameter je Arbeitsplan-Version
 * (Feature 047, MVP-061). Ein Parameter hat einen stabilen `code`, einen Typ
 * (number/measure/choice/text/date/bool) und typabhängige `constraints`
 * (required, default, min/max, unit, options). Mandantengrenze transitiv über
 * die Arbeitsplan-Version.
 *
 * Zusätzlich erhält `manufacturing_orders` eine `parameters`-Spalte für die im
 * Entwurf erfassten Parameterwerte; bei der Freigabe werden Definition + Werte
 * vollständig in `parameter_snapshot` eingefroren.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('procedure_parameter_definitions', function (Blueprint $table): void {
            $table->id();
            // Expliziter kurzer FK-Name (MySQL 64-Zeichen-Limit; SQLite-Dev verdeckt es).
            $table->foreignId('procedure_template_version_id')
                ->constrained('procedure_template_versions', indexName: 'ppd_ptv_fk')
                ->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('label', 191);
            $table->string('type', 12)->default('text'); // ParameterType
            $table->json('constraints')->nullable();      // required/default/min/max/unit/options
            $table->unsignedInteger('position')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['procedure_template_version_id', 'code'], 'proc_param_def_code_unique');
        });

        Schema::table('manufacturing_orders', function (Blueprint $table): void {
            // Im Entwurf erfasste Parameterwerte (code => value); bei Freigabe
            // eingefroren nach parameter_snapshot.
            $table->json('parameters')->nullable()->after('parameter_snapshot');
        });
    }

    public function down(): void {
        Schema::table('manufacturing_orders', function (Blueprint $table): void {
            $table->dropColumn('parameters');
        });
        Schema::dropIfExists('procedure_parameter_definitions');
    }
};
