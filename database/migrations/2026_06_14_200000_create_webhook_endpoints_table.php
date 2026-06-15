<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_14_200000_create_webhook_endpoints_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ausgehende Webhook-Endpunkte je Organisation (Feature 008).
 *
 * `secret` ist der HMAC-Signing-Key und wird symmetrisch mit APP_KEY
 * verschlüsselt at-rest abgelegt — DB-Dumps enthalten den Schlüssel also
 * nicht im Klartext. `events` ist die Liste abonnierter Webhook-Event-Keys
 * (App\Enums\Integration\WebhookEvent). Nach consecutive_failures-Schwelle
 * setzt der Versand disabled_at und deaktiviert den Endpunkt automatisch.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('webhook_endpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('label', 120);
            $table->string('url', 2048);
            $table->text('secret');
            $table->json('events');
            $table->boolean('active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_delivery_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'active'], 'webhook_endpoints_org_active_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('webhook_endpoints');
    }
};
