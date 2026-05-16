<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_14_120000_create_timesheets_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('timesheets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('work_date');
            // draft | submitted | signed | locked
            $table->string('status', 20)->default('draft');
            $table->string('customer_name')->nullable();
            $table->string('customer_role')->nullable();
            $table->string('customer_email')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('signed_ip', 64)->nullable();
            $table->foreignId('signature_attachment_id')->nullable()->constrained('attachments')->nullOnDelete();
            $table->string('signature_hash', 128)->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedInteger('totals_minutes')->default(0);
            $table->decimal('totals_material_net', 12, 2)->default(0);
            $table->string('magic_token', 80)->nullable()->unique();
            $table->timestamp('magic_expires_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'work_date']);
            $table->index(['user_id', 'work_date']);
            $table->index('status');
        });
    }

    public function down(): void {
        Schema::dropIfExists('timesheets');
    }
};
