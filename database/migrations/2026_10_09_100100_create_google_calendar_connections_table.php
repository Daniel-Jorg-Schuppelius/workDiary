<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_09_100100_create_google_calendar_connections_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Google-Kalender-Verbindung je Organisation (MVP-328, Bauturbo A8):
 * genau EINE OAuth-Verbindung je Org (unique), Tokens verschlüsselt at-rest
 * (encrypted-Cast, APP_KEY). Ziel-Kalender (`calendar_id`) wählbar — leer =
 * `primary`. Gesundheits-Spalten nach dem HasConnectionHealth-Standard
 * (MVP-178, Auto-Disable). Kurze, explizite FK-/Index-Namen
 * (MySQL-64-Zeichen-Limit, FK-Namen DB-weit eindeutig).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('google_calendar_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->unique('gcalc_org_unique')->constrained('organizations', indexName: 'gcalc_org_fk')->cascadeOnDelete();
            $table->text('access_token')->nullable();   // encrypted-Cast
            $table->text('refresh_token')->nullable();  // encrypted-Cast
            $table->timestamp('token_expires_at')->nullable();
            $table->string('scopes', 512)->nullable();  // Google-Scopes sind URLs
            $table->string('calendar_id')->nullable();
            $table->string('calendar_name')->nullable();
            $table->string('status', 16)->default('active'); // active / disconnected
            $table->timestamp('last_published_at')->nullable();
            $table->string('last_error', 300)->nullable();   // gekürzte Fehlerklasse/Meldung, nie Payload/Token
            $table->timestamp('last_error_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('disabled_at')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users', indexName: 'gcalc_conn_by_fk')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->foreignId('disconnected_by')->nullable()->constrained('users', indexName: 'gcalc_disc_by_fk')->nullOnDelete();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('google_calendar_connections');
    }
};
