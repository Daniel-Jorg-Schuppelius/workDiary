<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_260000_create_monitoring_tokens_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zugangstoken für das Diagnose-JSON externer Überwachung (MVP-1618):
 * gespeichert wird nur der SHA-256-Abdruck und ein Hinweis aus den ersten
 * sechs Zeichen; der Klartext erscheint einmal beim Anlegen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('monitoring_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->char('token_hash', 64)->unique();
            $table->string('token_hint', 8);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('last_audited_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('monitoring_tokens');
    }
};
