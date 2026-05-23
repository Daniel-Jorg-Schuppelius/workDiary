<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_24_120400_create_protocol_signature_tokens_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('protocol_signature_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('protocol_id')->constrained('protocols')->cascadeOnDelete();
            $table->string('role', 40);
            $table->string('signer_name', 120)->nullable();
            $table->string('signer_email', 180)->nullable();
            $table->char('token_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->foreignId('signed_signature_id')->nullable()
                ->constrained('protocol_signatures')->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique('token_hash', 'protocol_signature_tokens_hash_uniq');
            $table->index(['protocol_id', 'expires_at'], 'protocol_signature_tokens_protocol_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('protocol_signature_tokens');
    }
};
