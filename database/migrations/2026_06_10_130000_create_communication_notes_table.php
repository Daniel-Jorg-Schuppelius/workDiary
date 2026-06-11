<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_10_130000_create_communication_notes_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('communication_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('notable_type', 64);
            $table->unsignedBigInteger('notable_id');
            $table->string('type', 24);
            $table->string('direction', 12);
            $table->timestamp('occurred_at');
            $table->string('subject', 180);
            $table->text('body');
            $table->text('result')->nullable();
            $table->string('next_action', 180)->nullable();
            $table->timestamp('next_action_due_at')->nullable();
            $table->foreignId('next_action_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('next_action_completed_at')->nullable();
            $table->foreignId('next_action_completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visibility', 12)->default('internal');
            $table->boolean('confidential')->default(false);
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['notable_type', 'notable_id', 'occurred_at'], 'comm_notes_notable_idx');
            $table->index(['organization_id', 'occurred_at'], 'comm_notes_org_idx');
            $table->index(['next_action_user_id', 'next_action_due_at'], 'comm_notes_followup_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('communication_notes');
    }
};
