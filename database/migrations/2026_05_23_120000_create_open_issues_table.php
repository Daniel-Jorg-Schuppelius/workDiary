<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_23_120000_create_open_issues_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('open_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('subject_type', 64);
            $table->unsignedBigInteger('subject_id');
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_ref_id')->nullable();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('category', 40)->nullable();
            $table->string('severity', 20)->default('low');
            $table->string('status', 20)->default('open');
            $table->foreignId('assignee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->string('visibility', 12)->default('internal');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('closed_reason')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['subject_type', 'subject_id', 'status'], 'open_issues_subject_status_idx');
            $table->index(['assignee_user_id', 'status', 'due_at'], 'open_issues_assignee_idx');
            $table->index(['organization_id', 'status', 'severity'], 'open_issues_org_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('open_issues');
    }
};
