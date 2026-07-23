<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_26_100000_create_calendly_connections_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calendly-OAuth-Verbindung je Organisation (Feature 095): genau EINE
 * Verbindung je Org (unique), Tokens verschlüsselt at-rest (encrypted-Cast,
 * APP_KEY). `calendly_user_uri`/`calendly_organization_uri` sind die
 * Calendly-URIs des verbundenen Nutzers/der Organisation (Scope-Ziel der
 * Webhook-Subscription). Gesundheits-Spalten nach HasConnectionHealth-Standard
 * (MVP-178, Auto-Disable). Kurze, explizite FK-/Index-Namen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('calendly_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->unique('calc_org_unique')->constrained('organizations', indexName: 'calc_org_fk')->cascadeOnDelete();
            $table->text('access_token')->nullable();   // encrypted-Cast
            $table->text('refresh_token')->nullable();  // encrypted-Cast
            $table->timestamp('token_expires_at')->nullable();
            $table->string('scopes', 512)->nullable();
            $table->string('calendly_user_uri')->nullable();
            $table->string('calendly_organization_uri')->nullable();
            $table->string('status', 16)->default('active'); // active / disconnected
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_error', 300)->nullable();   // gekürzte Fehlerklasse, nie Payload/Token
            $table->timestamp('last_error_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('disabled_at')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users', indexName: 'calc_conn_by_fk')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->foreignId('disconnected_by')->nullable()->constrained('users', indexName: 'calc_disc_by_fk')->nullOnDelete();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('calendly_connections');
    }
};
