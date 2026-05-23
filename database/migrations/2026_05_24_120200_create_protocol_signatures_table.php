<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_24_120200_create_protocol_signatures_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('protocol_signatures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('protocol_id')->constrained('protocols')->cascadeOnDelete();
            $table->string('role', 40);
            $table->string('signer_name', 120);
            $table->string('signer_email', 180)->nullable();
            $table->unsignedBigInteger('signer_contact_id')->nullable();
            $table->timestamp('signed_at');
            $table->string('method', 20);
            $table->string('signature_image_path', 255)->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('hash', 64);
            $table->timestamps();

            $table->unique(['protocol_id', 'role', 'signer_name'], 'protocol_signatures_role_uniq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('protocol_signatures');
    }
};
