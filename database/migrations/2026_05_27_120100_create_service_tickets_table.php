<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_27_120100_create_service_tickets_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('service_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('ticket_no', 32);
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('sla_contract_id')->nullable()->constrained('sla_contracts')->nullOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('status', 20);
            $table->string('priority', 16);
            $table->string('source', 32)->default('manual');
            $table->string('source_reference', 120)->nullable();
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reported_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('reaction_due_at')->nullable();
            $table->timestamp('resolution_due_at')->nullable();
            $table->boolean('reaction_breached')->default(false);
            $table->boolean('resolution_breached')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'ticket_no'], 'service_tickets_uniq_no');
            $table->index(['organization_id', 'status', 'priority'], 'service_tickets_idx_org_status');
            $table->index(['organization_id', 'assigned_to_user_id', 'status'], 'service_tickets_idx_assignee');
            $table->index('asset_id', 'service_tickets_idx_asset');
            $table->index('customer_id', 'service_tickets_idx_customer');
            $table->index(['organization_id', 'resolution_due_at'], 'service_tickets_idx_due');
        });
    }

    public function down(): void {
        Schema::dropIfExists('service_tickets');
    }
};
