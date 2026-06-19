<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_16_180100_create_manufacturing_orders_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fertigungs-/Montageauftrag (Feature 047, MVP-062). Beim Freigeben werden
 * Arbeitsplan-Version, Variante, Auftragsparameter und die aufgelöste
 * Stückliste als unveränderliche Snapshots festgehalten; spätere Änderungen an
 * Basisdaten verändern laufende/abgeschlossene Aufträge nicht.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('manufacturing_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('number', 40)->nullable();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('article_variant_id')->nullable()->constrained('article_variants')->nullOnDelete();
            $table->decimal('target_qty', 18, 4);
            $table->string('unit', 20);
            $table->string('status', 16)->default('draft'); // ManufacturingOrderStatus
            $table->unsignedInteger('priority')->default(100);
            $table->date('planned_start')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('procurement_mode', 24)->nullable();

            // Eingefrorene Snapshots beim Freigeben:
            $table->foreignId('procedure_template_version_id')->nullable()->constrained('procedure_template_versions')->nullOnDelete();
            $table->json('bom_snapshot')->nullable();
            $table->json('variant_snapshot')->nullable();
            $table->json('parameter_snapshot')->nullable();

            $table->foreignId('procedure_run_id')->nullable()->constrained('procedure_runs')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('status');
            $table->unique(['organization_id', 'number'], 'manufacturing_orders_org_number_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('manufacturing_orders');
    }
};
