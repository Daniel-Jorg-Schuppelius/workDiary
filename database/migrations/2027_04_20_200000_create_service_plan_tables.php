<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_20_200000_create_service_plan_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leistungsplan je Kunde und Periode (Feature 199, MVP-1638–1640, MVP-1674).
 *
 * Der Stand einer Zelle kommt aus dem Auftrag der Periode; eigene Daten gibt
 * es nur, wo der Auftrag sie nicht trägt:
 *
 * - `service_templates`: Leistungsvorlagen der Organisation (Eintragsart +
 *   Produktgruppe, Periodenart, Vorgabe Pflicht/Wahl, Bezeichnung ×5) aus dem
 *   Branchenprofil (`service_catalog`); der Dialog „Kunde anlegen“ schlägt sie
 *   als Leistungsumfang vor.
 * - `customer_service_waivers`: „entfällt“ für eine Zelle ohne Auftrag (Kunde,
 *   Leistung, Periode, Grund, von, am).
 * - `customer_service_period_notes`: „Unterlagen da am“ und Bemerkung je Zelle.
 *
 * Leistung = Eintragsart + Produktgruppe (`product_group_code` leer = jede),
 * wie im Leistungsumfang `customer_services` (Feature 197).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('service_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('entry_type_id')->constrained('entry_types')->cascadeOnDelete();
            $table->string('product_group_code', 64)->nullable();
            $table->string('period_kind', 16);
            $table->boolean('is_mandatory')->default(true);
            $table->string('label', 150);
            $table->json('label_i18n')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'entry_type_id'], 'st_entry_type_idx');
        });

        Schema::create('customer_service_waivers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('entry_type_id')->constrained('entry_types')->cascadeOnDelete();
            $table->string('product_group_code', 64)->nullable();
            $table->string('period_key', 10);
            $table->text('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'customer_id', 'period_key'], 'csw_customer_period_idx');
        });

        Schema::create('customer_service_period_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('entry_type_id')->constrained('entry_types')->cascadeOnDelete();
            $table->string('product_group_code', 64)->nullable();
            $table->string('period_key', 10);
            $table->date('documents_received_on')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'customer_id', 'period_key'], 'cspn_customer_period_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('customer_service_period_notes');
        Schema::dropIfExists('customer_service_waivers');
        Schema::dropIfExists('service_templates');
    }
};
