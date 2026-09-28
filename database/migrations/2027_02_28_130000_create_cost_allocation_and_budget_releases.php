<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_130000_create_cost_allocation_and_budget_releases.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-982/983: Umlageschlüssel zwischen Kostenstellen und Freigabe der Budgets. */
return new class extends Migration {
    public function up(): void {
        Schema::create('cost_allocation_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'cost_alloc_org_fk')->cascadeOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->foreignId('source_cost_center_id')->constrained('cost_centers', indexName: 'cost_alloc_source_fk')->cascadeOnDelete();
            $table->foreignId('target_cost_center_id')->constrained('cost_centers', indexName: 'cost_alloc_target_fk')->cascadeOnDelete();
            $table->decimal('share_percent', 5, 2);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'cost_alloc_creator_fk')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'fiscal_year', 'source_cost_center_id', 'target_cost_center_id'], 'cost_alloc_unique');
        });

        Schema::create('accounting_budget_releases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'budget_rel_org_fk')->cascadeOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers', indexName: 'budget_rel_cc_fk')->cascadeOnDelete();
            $table->string('status', 20);
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users', indexName: 'budget_rel_releaser_fk')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users', indexName: 'budget_rel_reopener_fk')->nullOnDelete();
            $table->string('reopen_reason', 500)->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'fiscal_year'], 'budget_rel_org_year_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('accounting_budget_releases');
        Schema::dropIfExists('cost_allocation_keys');
    }
};
