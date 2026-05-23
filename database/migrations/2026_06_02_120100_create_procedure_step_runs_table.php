<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_02_120100_create_procedure_step_runs_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('procedure_step_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('procedure_run_id')->constrained('procedure_runs')->cascadeOnDelete();
            $table->foreignId('procedure_step_def_id')->constrained('procedure_step_defs')->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->json('value_json')->nullable();
            $table->foreignId('executed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('executed_at')->nullable();
            $table->foreignId('second_person_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('second_person_signed_at')->nullable();
            $table->unsignedBigInteger('proof_attachment_id')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('deviation_id')->nullable();
            $table->timestamps();

            $table->unique(['procedure_run_id', 'procedure_step_def_id'], 'procedure_step_runs_uniq');
            $table->index('procedure_run_id', 'procedure_step_runs_run_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('procedure_step_runs');
    }
};
