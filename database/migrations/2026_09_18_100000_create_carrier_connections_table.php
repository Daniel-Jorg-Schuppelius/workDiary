<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_18_100000_create_carrier_connections_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carrier-Anbindung je Organisation (Feature 059, MVP-128): Zugangsdaten für den
 * Paketdienst (DHL Paket u. a.), at-rest verschlüsselt (`encrypted`-Cast,
 * APP_KEY!). `billing_number` = Abrechnungs-/Kostenstellenreferenz des Carriers;
 * `sandbox` schaltet auf die Testumgebung.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('carrier_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'carrierconn_org_fk')->cascadeOnDelete();
            $table->string('carrier', 24);              // dhl|gls|dpd
            $table->string('name');
            $table->text('credentials');                // encrypted:array (user/pass/api_key/client_id/secret)
            $table->string('billing_number')->nullable();
            $table->boolean('sandbox')->default(false);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'carrierconn_creator_fk')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id', 'carrierconn_org_idx');
            $table->unique(['organization_id', 'carrier'], 'carrierconn_org_carrier_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('carrier_connections');
    }
};
