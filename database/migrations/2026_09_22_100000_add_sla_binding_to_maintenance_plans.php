<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_22_100000_add_sla_binding_to_maintenance_plans.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bindet einen Wartungsplan optional an einen SLA-Vertrag und steuert, was der
 * Fälligkeits-Scanner bei Fälligkeit erzeugt (Feature 010 → Rang 43):
 *   - `sla_contract_id`  — der Vertrag, dessen Pflichttermin dieser Plan abbildet.
 *   - `is_contractual`   — Kennzeichen „Vertragspflicht" (Nachweis/Filter).
 *   - `due_action`       — `none` (nur Audit, Default) | `ticket` (Service-Ticket).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('maintenance_plans', function (Blueprint $table): void {
            $table->foreignId('sla_contract_id')->nullable()
                ->constrained('sla_contracts', indexName: 'maintplan_sla_fk')->nullOnDelete();
            $table->boolean('is_contractual')->default(false);
            $table->string('due_action', 20)->default('none');
        });
    }

    public function down(): void {
        Schema::table('maintenance_plans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sla_contract_id');
            $table->dropColumn(['is_contractual', 'due_action']);
        });
    }
};
