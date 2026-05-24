<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_04_160000_create_onboarding_progress_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('onboarding_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('step_code', 64);
            $table->string('state', 16)->default('open'); // open|done|skipped
            $table->timestamp('done_at')->nullable();
            $table->foreignId('done_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('skipped_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'step_code'], 'uniq_onboarding_org_step');
            $table->index(['organization_id', 'state'], 'idx_onboarding_org_state');
        });
    }

    public function down(): void {
        Schema::dropIfExists('onboarding_progress');
    }
};
