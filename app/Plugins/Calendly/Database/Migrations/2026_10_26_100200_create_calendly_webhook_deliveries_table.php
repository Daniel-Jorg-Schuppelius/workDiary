<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_26_100200_create_calendly_webhook_deliveries_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Webhook-Deduplizierung für Calendly (Feature 095): jede Zustellung wird VOR
 * der Verarbeitung mit dem Hash des Raw-Bodys persistiert — Replays enden
 * idempotent (Unique-Constraint). Calendly liefert KEINE Delivery-ID, daher
 * ist `delivery_hash` = sha256(rawBody). Kurze, explizite Index-Namen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('calendly_webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->string('delivery_hash', 191);
            $table->string('event_name', 64)->nullable();
            $table->string('invitee_uri')->nullable();
            $table->foreignId('organization_id')->nullable()->constrained('organizations', indexName: 'caldel_org_fk')->nullOnDelete();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'delivery_hash'], 'caldel_org_hash_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('calendly_webhook_deliveries');
    }
};
