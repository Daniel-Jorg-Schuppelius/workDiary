<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_17_100100_create_user_badges_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RFID-/NFC-Badge-Zuordnung zu Nutzern (Feature 061, MVP-130): die Badge-Kennung
 * wird ausschließlich als SHA-256-Hash gespeichert (keine Klartext-Kennungen in
 * DB/Logs). Ein verlorener Badge wird über `revoked_at` gesperrt und einem neuen
 * Datensatz neu zugeordnet — ohne Datenverlust.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('user_badges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'userbadge_org_fk')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users', indexName: 'userbadge_user_fk')->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('badge_hash', 64);
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'userbadge_creator_fk')->nullOnDelete();
            $table->timestamps();

            // Eine aktive Kennung ist je Organisation eindeutig (ein Badge → ein Nutzer).
            $table->unique(['organization_id', 'badge_hash'], 'userbadge_org_hash_unique');
            $table->index(['organization_id', 'user_id'], 'userbadge_org_user_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('user_badges');
    }
};
