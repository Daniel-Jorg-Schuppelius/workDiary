<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_10_160000_create_surcharge_rules_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zuschlagsregeln pro Organisation (Feature 005, MVP).
 *
 * - kind: night|saturday|sunday|holiday|custom (SurchargeKind)
 * - window_start/window_end: Zeitfenster (nur night/custom); Fenster über
 *   Mitternacht (z. B. 23:00–06:00) wird vom SurchargeCalculator gesplittet.
 * - percentage: Zuschlagssatz in Prozent (z. B. 25.00)
 * - wage_type_code: Lohnart-Nummer/-Code für DATEV/Lexware-Übergabe
 * - priority: Tie-Breaker bei Überlappung mit GLEICHEM Prozentsatz
 *   (höhere priority gewinnt); primär gewinnt der höchste Prozentsatz —
 *   siehe SurchargeCalculator (Stacking: max, nicht additiv).
 * - valid_from/valid_until: Gültigkeitszeitraum (DATE, beide inklusiv)
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('surcharge_rules', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $t->string('code', 20);
            $t->string('label', 100);
            $t->string('kind', 16);
            $t->time('window_start')->nullable();
            $t->time('window_end')->nullable();
            $t->decimal('percentage', 5, 2)->default(0);
            $t->string('wage_type_code', 20)->nullable();
            $t->integer('priority')->default(0);
            $t->boolean('active')->default(true);
            $t->date('valid_from')->nullable();
            $t->date('valid_until')->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->unique(['organization_id', 'code'], 'sur_rules_org_code_uq');
            $t->index(['organization_id', 'active', 'kind'], 'sur_rules_org_active_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('surcharge_rules');
    }
};
