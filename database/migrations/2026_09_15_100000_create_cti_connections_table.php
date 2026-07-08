<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_15_100000_create_cti_connections_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Telefonie-/CTI-Anbindung je Organisation (Feature 056, MVP-118): providerneutral
 * (sipgate als Referenz, generisch). Der eingehende Webhook ist über einen
 * Token im Pfad autorisiert — gespeichert wird nur der SHA-256-Hash (Klartext
 * einmalig, Muster wie `location_device_tokens`). Es werden ausschließlich
 * Metadaten verarbeitet, nie Gesprächsinhalte (Datenschutz, DoD 056).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('cti_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'cticonn_org_fk')->cascadeOnDelete();
            $table->string('name');
            $table->string('provider', 24)->default('generic'); // sipgate|placetel|starface|generic
            $table->string('webhook_token_hash', 64)->unique('cticonn_token_unique');
            $table->boolean('active')->default(true);
            $table->timestamp('last_event_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'cticonn_creator_fk')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id', 'cticonn_org_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('cti_connections');
    }
};
