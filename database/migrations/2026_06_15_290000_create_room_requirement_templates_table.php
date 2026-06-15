<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_15_290000_create_room_requirement_templates_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raumbezogene Anforderungs-Vorlagen je Gewerk (Feature 042 / 027).
 *
 * Branchenprofile können raum-/objektbezogene Anforderungen als organisationsweite
 * Vorlagen vorbelegen, ohne dass beim Import bereits Räume existieren müssen.
 * Beim Anlegen/Pflegen von Räumen lassen sich diese Vorlagen als
 * {@see \App\Models\RoomRequirement} übernehmen (1:n über room_requirements).
 *
 * Idempotenz über (organization_id, code).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('room_requirement_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 60);
            $table->string('kind', 30);
            $table->string('label', 120);
            $table->string('level', 60)->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'code'], 'room_req_tpl_uq_code');
            $table->index(['organization_id', 'kind'], 'room_req_tpl_idx_kind');
        });
    }

    public function down(): void {
        Schema::dropIfExists('room_requirement_templates');
    }
};
