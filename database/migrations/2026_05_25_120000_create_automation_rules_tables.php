<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_25_120000_create_automation_rules_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('automation_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('trigger_event', 64);
            $table->json('conditions');
            $table->json('actions');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'trigger_event', 'is_active'], 'automation_rules_lookup_idx');
        });

        Schema::create('automation_rule_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rule_id')->constrained('automation_rules')->cascadeOnDelete();
            $table->string('subject_type', 191);
            $table->unsignedBigInteger('subject_id');
            $table->string('decision', 32); // matched | no_match | error
            $table->json('log')->nullable();
            $table->timestamp('ran_at')->useCurrent();

            $table->index(['subject_type', 'subject_id'], 'automation_runs_subject_idx');
            $table->index(['rule_id', 'ran_at'], 'automation_runs_rule_time_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('automation_rule_runs');
        Schema::dropIfExists('automation_rules');
    }
};
