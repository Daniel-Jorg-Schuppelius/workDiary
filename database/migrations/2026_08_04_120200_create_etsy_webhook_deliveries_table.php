<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_04_120200_create_etsy_webhook_deliveries_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Webhook-Deduplizierung für Etsy (Feature 101, MVP-496): jede Zustellung
 * wird VOR der Verarbeitung mit dem Hash des Raw-Bodys persistiert —
 * Svix-Retries und Portal-Replays enden idempotent (Unique-Constraint).
 * Die Svix-`webhook-id` dient nur der Diagnose. Kurze, explizite
 * Index-Namen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('etsy_webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->string('delivery_hash', 191);
            $table->string('webhook_id', 64)->nullable();
            $table->string('event_type', 32)->nullable();
            $table->unsignedBigInteger('receipt_id')->nullable();
            $table->foreignId('organization_id')->nullable()->constrained('organizations', indexName: 'etsydel_org_fk')->nullOnDelete();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'delivery_hash'], 'etsydel_org_hash_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('etsy_webhook_deliveries');
    }
};
