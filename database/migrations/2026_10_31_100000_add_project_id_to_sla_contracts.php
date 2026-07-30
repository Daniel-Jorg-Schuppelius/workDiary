<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_31_100000_add_project_id_to_sla_contracts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W5.4: optionale Projektbindung für SLA-Verträge. Ein projektgebundener
 * Vertrag gewinnt bei der Auflösung vor Kunden- und Org-Default-Vertrag
 * ({@see \App\Services\ServiceTicket\SlaTimer::resolveContract()}).
 * NULL = wie bisher kunden- bzw. org-weit.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('sla_contracts', function (Blueprint $table): void {
            $table->foreignId('project_id')->nullable()
                ->constrained('projects', indexName: 'fk_sla_contracts_project')->nullOnDelete();
            $table->index(['organization_id', 'project_id', 'is_active'], 'sla_contracts_idx_project');
        });
    }

    public function down(): void {
        Schema::table('sla_contracts', function (Blueprint $table): void {
            $table->dropIndex('sla_contracts_idx_project');
            $table->dropConstrainedForeignId('project_id');
        });
    }
};
