<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_24_120000_create_protocols_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('protocols', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('type', 40);
            $table->unsignedBigInteger('template_id')->nullable();
            $table->unsignedInteger('template_version')->nullable();
            $table->string('subject_type', 64);
            $table->unsignedBigInteger('subject_id');
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->text('state_initial')->nullable();
            $table->text('state_final')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('supersedes_id')->nullable()->constrained('protocols')->nullOnDelete();
            $table->string('visibility', 12)->default('internal');
            $table->timestamp('occurred_at');
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'occurred_at'], 'protocols_subject_idx');
            $table->index(['organization_id', 'type', 'status'], 'protocols_org_type_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('protocols');
    }
};
