<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_26_100100_create_calendly_webhook_subscriptions_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calendly-Webhook-Subscription (Feature 095): trägt die Auflösung
 * Callback-`url_token` → Organisation + `signing_key`. Der opake `url_token`
 * steht in der Webhook-URL (`/api/webhooks/calendly/{token}`) und schlägt die
 * Zeile in O(1) nach — die Organisation wird NIE aus dem Payload geraten
 * (Mandantensicherheit), und der `signing_key` ist eindeutig. `signing_key`
 * ist verschlüsselt at-rest. `calendly_subscription_uri` ist die von Calendly
 * zurückgegebene URI (für DELETE/List/Heilung).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('calendly_webhook_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'calsub_org_fk')->cascadeOnDelete();
            $table->foreignId('calendly_connection_id')->constrained('calendly_connections', indexName: 'calsub_conn_fk')->cascadeOnDelete();
            $table->string('url_token', 64)->unique('calsub_token_unique');
            $table->text('signing_key');                          // encrypted-Cast
            $table->string('calendly_subscription_uri')->nullable();
            $table->string('scope', 16)->default('organization'); // organization / user
            $table->json('events');
            $table->string('status', 16)->default('active');      // active / disabled
            $table->timestamp('last_delivery_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'calsub_org_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('calendly_webhook_subscriptions');
    }
};
