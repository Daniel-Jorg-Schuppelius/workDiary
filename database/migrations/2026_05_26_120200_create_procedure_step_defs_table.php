<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_26_120200_create_procedure_step_defs_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('procedure_step_defs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('procedure_template_version_id')->constrained('procedure_template_versions')->cascadeOnDelete();
            $table->unsignedInteger('sort_order');
            $table->string('code', 60);
            $table->string('step_type', 40);
            $table->string('label', 180);
            $table->text('description')->nullable();
            $table->boolean('required')->default(true);
            $table->boolean('blocking')->default(true);
            $table->json('config')->nullable();
            $table->string('required_role', 40)->nullable();
            $table->string('required_qualification_code', 60)->nullable();
            $table->boolean('requires_second_person')->default(false);
            $table->string('requires_proof_type', 40)->nullable();
            $table->timestamps();

            $table->unique(['procedure_template_version_id', 'code'], 'procedure_step_defs_code_uniq');
            $table->index(['procedure_template_version_id', 'sort_order'], 'procedure_step_defs_order_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('procedure_step_defs');
    }
};
