<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_26_140000_create_maintenance_plans_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('maintenance_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('code', 60);
            $table->string('label', 180);
            $table->string('interval_kind', 20);
            $table->unsignedInteger('interval_value');
            $table->unsignedSmallInteger('tolerance_days')->default(0);
            $table->string('procedure_template_code', 60)->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->date('next_due_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['asset_id', 'code'], 'maintenance_plans_uniq_code_per_asset');
            $table->index(['organization_id', 'is_active', 'next_due_on'], 'maintenance_plans_idx_due');
        });
    }

    public function down(): void {
        Schema::dropIfExists('maintenance_plans');
    }
};
