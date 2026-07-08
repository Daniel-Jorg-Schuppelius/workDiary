<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_100100_create_cost_center_rules_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kostenstellen-Mapping für den geprüften Zeitexport (MVP-019, Rang 35 —
 * rescoped): Regeln je Organisation mit Quelle Benutzer ODER Team (der Export
 * aggregiert je User; Projekt/Kunde existieren auf der Zeile nicht). Genau
 * eine der Quellspalten ist gesetzt — beide leer = Org-Default-Regel.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('cost_center_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('cost_center', 32);
            $table->integer('priority')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'priority'], 'ccr_org_prio_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('cost_center_rules');
    }
};
