<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_14_130000_create_sla_violations_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sla_violations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('service_ticket_id')->constrained('service_tickets')->cascadeOnDelete();
            $table->foreignId('sla_contract_id')->nullable()->constrained('sla_contracts')->nullOnDelete();
            $table->string('kind', 32); // responseTime | resolutionTime
            $table->timestamp('target_at')->nullable();
            $table->timestamp('breached_at')->nullable();
            $table->integer('overdue_minutes')->default(0);
            $table->string('priority', 32)->nullable();
            $table->string('cause', 191)->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Genau eine Violation je Ticket + Typ (Idempotenz der Erkennung).
            $table->unique(['service_ticket_id', 'kind'], 'sla_violations_uniq_ticket_kind');
            $table->index(['organization_id', 'kind'], 'sla_violations_idx_org_kind');
            $table->index(['organization_id', 'breached_at'], 'sla_violations_idx_org_breach');
        });
    }

    public function down(): void {
        Schema::dropIfExists('sla_violations');
    }
};
