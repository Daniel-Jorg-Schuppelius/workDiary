<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_21_140000_create_rooms_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('rooms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->cascadeOnDelete();

            $table->string('name', 160);
            $table->string('code', 32)->nullable();
            $table->string('building', 120)->nullable();
            $table->string('floor', 32)->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();

            // Ausstattung als JSON-Liste, z. B. ["beamer","whiteboard","video_conf"]
            $table->json('equipment')->nullable();

            // Hex-Farbe zur Darstellung im Belegungsraster (z. B. "#3b82f6")
            $table->string('color', 9)->nullable();

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
            $table->unique(['organization_id', 'code']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('rooms');
    }
};
