<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_02_120200_create_procedure_run_events_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('procedure_run_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('procedure_run_id')->constrained('procedure_runs')->cascadeOnDelete();
            $table->foreignId('procedure_step_run_id')->nullable()->constrained('procedure_step_runs')->cascadeOnDelete();
            $table->string('event_type', 60);
            $table->json('payload')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['procedure_run_id', 'event_type'], 'procedure_run_events_run_type_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('procedure_run_events');
    }
};
