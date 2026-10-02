<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_04_120000_create_etsy_connections_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etsy-OAuth-Verbindung je Organisation (Feature 101, MVP-494): genau EINE
 * Verbindung je Org (unique) und je Shop (unique — verhindert, dass zwei
 * Organisationen denselben Etsy-Shop anbinden). Tokens verschlüsselt at-rest
 * (encrypted-Cast, APP_KEY). `webhook_token` = opaker URL-Bestandteil des
 * Ingest-Endpunkts. `checkpoints` = Sync-Aufholpunkte (Epoch-Sekunden).
 * Gesundheits-Spalten nach HasConnectionHealth-Standard (MVP-178,
 * Auto-Disable). Kurze, explizite FK-/Index-Namen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('etsy_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->unique('etsyc_org_unique')->constrained('organizations', indexName: 'etsyc_org_fk')->cascadeOnDelete();
            $table->unsignedBigInteger('shop_id')->nullable()->unique('etsyc_shop_unique');
            $table->string('shop_name')->nullable();
            $table->unsignedBigInteger('etsy_user_id')->nullable();
            $table->text('access_token')->nullable();   // encrypted-Cast
            $table->text('refresh_token')->nullable();  // encrypted-Cast
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('refresh_issued_at')->nullable(); // 90-Tage-Reconnect-Warnung
            $table->string('scopes', 512)->nullable();
            $table->string('status', 16)->default('active'); // active / disconnected
            $table->string('webhook_token', 64)->nullable()->unique('etsyc_hook_unique');
            $table->json('checkpoints')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('last_sync_counters')->nullable();
            $table->string('last_error', 300)->nullable();   // gekürzte Fehlerklasse, nie Payload/Token
            $table->timestamp('last_error_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('disabled_at')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users', indexName: 'etsyc_conn_by_fk')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->foreignId('disconnected_by')->nullable()->constrained('users', indexName: 'etsyc_disc_by_fk')->nullOnDelete();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('etsy_connections');
    }
};
