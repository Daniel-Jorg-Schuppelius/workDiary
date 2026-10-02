<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_18_100000_create_lexoffice_webhook_deliveries_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Webhook-Deduplizierung für Lexoffice-Event-Subscriptions (Audit 2026-08,
 * Welle 1.3): jede Zustellung wird VOR der Verarbeitung persistiert — Replays
 * enden idempotent (Unique-Constraint). Lexoffice sendet keine Delivery-ID,
 * dedupliziert wird über den Inhalts-Hash des Raw-Bodys (eventDate macht
 * legitime Folge-Events unterscheidbar). Kurze, explizite Index-Namen
 * (MySQL-64-Zeichen-Limit).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('lexoffice_webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->string('delivery_hash', 191);
            $table->string('event_type', 100)->nullable();
            $table->string('resource_id', 191)->nullable();
            $table->foreignId('organization_id')->nullable()->constrained('organizations', indexName: 'lwdel_org_fk')->nullOnDelete();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique('delivery_hash', 'lwdel_hash_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('lexoffice_webhook_deliveries');
    }
};
