<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_14_200100_create_webhook_deliveries_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zustellprotokoll je Webhook-Endpunkt (Feature 008). Eine Zeile pro
 * Auslieferungsversuch eines fachlichen Ereignisses: Status, HTTP-Code,
 * Versuchsnummer und ein gekürzter Antwort-Auszug für die Diagnose.
 * payload_hash bindet das Protokoll an die signierte Nutzlast, ohne den
 * (ggf. personenbezogenen) Inhalt selbst zu speichern.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('webhook_endpoint_id')->constrained('webhook_endpoints')->cascadeOnDelete();
            $table->string('event', 80);
            $table->string('payload_hash', 64);
            $table->string('status', 12)->default('pending');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->string('response_excerpt', 500)->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['webhook_endpoint_id', 'created_at'], 'webhook_deliveries_endpoint_idx');
            $table->index(['organization_id', 'status'], 'webhook_deliveries_org_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('webhook_deliveries');
    }
};
