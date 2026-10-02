<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_08_100000_create_mcp_oauth_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1065: OAuth für den MCP-Server — öffentliche Clients aus der
 * dynamischen Registrierung, Autorisierungscodes (PKCE) und Refresh-Token.
 * Das Zugriffstoken selbst ist ein Sanctum-Token. Refresh-Token bilden je
 * Anmeldung eine Familie: Rotation markiert das alte, ein erneutes Einlösen
 * oder ein Widerruf beendet die ganze Familie.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('mcp_oauth_clients', function (Blueprint $table): void {
            $table->id();
            $table->string('client_key', 64)->unique();
            $table->string('name', 100);
            $table->json('redirect_uris');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('mcp_oauth_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mcp_oauth_client_id')->constrained('mcp_oauth_clients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('code_hash', 64)->unique();
            $table->text('redirect_uri');
            $table->string('code_challenge', 128);
            $table->json('scopes');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            // Token-Familie aus der Einlösung — ein zweites Einlösen widerruft sie.
            $table->string('family', 36)->nullable();
            $table->timestamps();
        });

        Schema::create('mcp_oauth_refresh_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mcp_oauth_client_id')->constrained('mcp_oauth_clients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Leer, sobald das Zugriffstoken weg ist (rotiert oder in der Tokenverwaltung widerrufen).
            $table->foreignId('personal_access_token_id')->nullable()->constrained('personal_access_tokens', 'id', 'mcp_oauth_refresh_pat_fk')->nullOnDelete();
            $table->string('family', 36)->index();
            $table->string('token_hash', 64)->unique();
            $table->json('scopes');
            $table->timestamp('expires_at');
            $table->timestamp('rotated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('mcp_oauth_refresh_tokens');
        Schema::dropIfExists('mcp_oauth_codes');
        Schema::dropIfExists('mcp_oauth_clients');
    }
};
