<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_25_190000_create_asset_inspection_rounds_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-899: Prüfmittelrunden mit eingefrorener Soll-Liste. */
return new class extends Migration {
    public function up(): void {
        Schema::create('asset_inspection_rounds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('location_text', 255)->nullable();
            $table->string('category_code', 60)->nullable();
            $table->foreignId('asset_compliance_profile_id')->nullable()->constrained('asset_compliance_profiles')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->date('due_until');
            $table->string('status', 20)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'status'], 'asset_insp_rounds_org_status_idx');
        });

        Schema::create('asset_inspection_round_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_inspection_round_id')->constrained('asset_inspection_rounds', indexName: 'asset_insp_round_items_round_fk')->cascadeOnDelete();
            $table->foreignId('asset_compliance_assignment_id')->constrained('asset_compliance_assignments', indexName: 'asset_insp_round_items_assign_fk')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets', indexName: 'asset_insp_round_items_asset_fk')->cascadeOnDelete();
            $table->date('due_on')->nullable();
            $table->foreignId('asset_inspection_event_id')->nullable()->constrained('asset_inspection_events', indexName: 'asset_insp_round_items_event_fk')->nullOnDelete();
            $table->timestamps();
            $table->unique(['asset_inspection_round_id', 'asset_compliance_assignment_id'], 'asset_insp_round_items_uniq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('asset_inspection_round_items');
        Schema::dropIfExists('asset_inspection_rounds');
    }
};
