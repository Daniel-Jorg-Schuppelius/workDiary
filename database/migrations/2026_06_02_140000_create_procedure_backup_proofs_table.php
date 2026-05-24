<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_02_140000_create_procedure_backup_proofs_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('procedure_backup_proofs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('procedure_step_run_id')->unique('procedure_backup_proofs_step_uniq')->constrained('procedure_step_runs')->cascadeOnDelete();
            $table->string('backup_scope', 40);
            $table->string('source_label', 180);
            $table->timestamp('taken_at');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('checksum_algo', 20)->nullable();
            $table->string('checksum_value', 128)->nullable();
            $table->string('storage_target', 20);
            $table->unsignedBigInteger('attachment_id')->nullable();
            $table->string('external_ref', 255)->nullable();
            $table->boolean('verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('verify_method', 40);
            $table->text('verify_note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void {
        Schema::dropIfExists('procedure_backup_proofs');
    }
};
