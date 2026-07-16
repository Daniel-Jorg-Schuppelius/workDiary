<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_21_100300_create_domain_accounting_event_invoice_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accounting-Journal, Ereignis-Durable-Store und capability-gegatete
 * Rechnungsprojektion (Feature 083, MVP-391/392/393).
 *
 *  - `domain_accounting_entries`: read-only Projektion von `QueryAccountingList`;
 *    WorkDiary erzeugt daraus KEINE steuerliche Rechnung.
 *  - `domain_events`: Event-ID/Status/Rohhash werden DAUERHAFT gespeichert,
 *    BEVOR `DeleteEvent` als Acknowledge gesendet wird.
 *  - `domain_external_invoices`: Schema bleibt leer, bis ein realer Vertrag
 *    Rechnungsliste/-PDF eindeutig belegt (Blocked-State, MVP-393).
 *
 * Präfixe `dae_`/`dev_`/`dei_`.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('domain_accounting_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('connection_id')->constrained('domain_provider_connections', indexName: 'dae_connection_fk')->cascadeOnDelete();

            $table->string('external_user', 190);
            $table->string('accounting_id', 190)->nullable();
            $table->foreignId('reseller_account_id')->nullable()->constrained('domain_reseller_accounts', indexName: 'dae_reseller_fk')->nullOnDelete();
            $table->foreignId('domain_projection_id')->nullable()->constrained('domain_projections', indexName: 'dae_domain_fk')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers', indexName: 'dae_customer_fk')->nullOnDelete();

            $table->date('entry_date')->nullable();
            $table->string('type', 64)->nullable();
            $table->string('description', 512)->nullable();
            $table->string('reference', 190)->nullable();
            $table->decimal('quantity', 12, 3)->nullable();
            $table->decimal('net_amount', 15, 2)->nullable();
            $table->decimal('vat_rate', 6, 3)->nullable();
            $table->decimal('tax_amount', 15, 2)->nullable();
            $table->string('currency', 3)->nullable();

            $table->char('raw_hash', 64);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'connection_id', 'raw_hash'], 'dae_org_conn_hash_uq');
            $table->index(['organization_id', 'entry_date'], 'dae_org_date_idx');
        });

        Schema::create('domain_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('connection_id')->constrained('domain_provider_connections', indexName: 'dev_connection_fk')->cascadeOnDelete();

            $table->string('external_event_id', 190);
            $table->string('event_class', 64)->nullable();  // DOMAIN | CONTACT | ...
            $table->string('event_action', 64)->nullable();
            $table->string('object', 253)->nullable();
            $table->string('status', 20)->default('stored'); // stored | acknowledged | failed
            $table->char('raw_hash', 64);
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('stored_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'connection_id', 'external_event_id'], 'dev_org_conn_event_uq');
            $table->index(['organization_id', 'status'], 'dev_org_status_idx');
        });

        Schema::create('domain_external_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('connection_id')->constrained('domain_provider_connections', indexName: 'dei_connection_fk')->cascadeOnDelete();

            $table->string('external_invoice_id', 190);
            $table->foreignId('reseller_account_id')->nullable()->constrained('domain_reseller_accounts', indexName: 'dei_reseller_fk')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers', indexName: 'dei_customer_fk')->nullOnDelete();

            $table->date('invoice_date')->nullable();
            $table->string('status', 32)->nullable();
            $table->decimal('net_amount', 15, 2)->nullable();
            $table->decimal('tax_amount', 15, 2)->nullable();
            $table->decimal('gross_amount', 15, 2)->nullable();
            $table->string('currency', 3)->nullable();

            // Ins DMS übernommenes Originaldokument (erst nach Capability-Nachweis).
            $table->foreignId('document_id')->nullable()->constrained('documents', indexName: 'dei_document_fk')->nullOnDelete();
            $table->string('origin', 64)->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'connection_id', 'external_invoice_id'], 'dei_org_conn_invoice_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('domain_external_invoices');
        Schema::dropIfExists('domain_events');
        Schema::dropIfExists('domain_accounting_entries');
    }
};
