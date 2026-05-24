<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_02_150000_create_procedure_deviations_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('procedure_deviations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('procedure_step_run_id')->unique('procedure_deviations_step_uniq')->constrained('procedure_step_runs')->cascadeOnDelete();
            $table->string('deviation_type', 40);
            $table->string('severity', 20);
            $table->text('reason_text');
            $table->string('proposed_action', 40)->nullable();
            $table->unsignedBigInteger('open_issue_id')->nullable();
            $table->unsignedBigInteger('follow_up_diary_entry_id')->nullable();
            $table->foreignId('risk_accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('risk_accepted_at')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'deviation_type', 'severity'], 'procedure_deviations_org_type_sev_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('procedure_deviations');
    }
};
