<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_26_140100_create_maintenance_plan_templates_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('maintenance_plan_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 60);
            $table->string('label', 180);
            $table->string('asset_class', 40)->nullable();
            $table->string('category_code', 60)->nullable();
            $table->string('interval_kind', 20);
            $table->unsignedInteger('interval_value');
            $table->unsignedSmallInteger('tolerance_days')->default(0);
            $table->string('procedure_template_code', 60)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code'], 'maintenance_plan_templates_uniq_code');
            $table->index(['organization_id', 'asset_class'], 'maintenance_plan_templates_idx_class');
        });
    }

    public function down(): void {
        Schema::dropIfExists('maintenance_plan_templates');
    }
};
