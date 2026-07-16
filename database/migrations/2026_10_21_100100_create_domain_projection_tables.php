<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_21_100100_create_domain_projection_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projektionen des DomainReselling-Kontos (Feature 083, MVP-386/387):
 * Subuser/Reseller, Domains und Registry-Kontakte. DomainReselling bleibt
 * führend; WorkDiary hält nur Projektionen (Revision + `raw_hash`) plus die
 * eigene Kundenzuordnung. Kein Auth-Code wird dauerhaft gespeichert.
 *
 * Präfixe `dra_`/`dp_`/`dcp_` (DB-weit eindeutig). Lange Domainnamen sind für
 * einen MySQL-Unique-Index zu breit → deterministische Hash-Spalte.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('domain_reseller_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('connection_id')->constrained('domain_provider_connections', indexName: 'dra_connection_fk')->cascadeOnDelete();

            $table->string('external_user', 190);
            $table->string('parent_user', 190)->nullable();
            $table->unsignedSmallInteger('depth')->default(0);
            $table->string('user_class', 64)->nullable();
            $table->boolean('active')->default(true);
            $table->string('currency', 3)->nullable();
            $table->decimal('balance_snapshot', 15, 2)->nullable();
            $table->timestamp('balance_at')->nullable();

            // Optionale WorkDiary-Kundenzuordnung (Domains werden dadurch
            // gruppiert, bleiben aber „geführt unter Subuser …" gekennzeichnet).
            $table->foreignId('customer_id')->nullable()->constrained('customers', indexName: 'dra_customer_fk')->nullOnDelete();

            $table->string('raw_hash', 64)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'connection_id', 'external_user'], 'dra_org_conn_user_uq');
            $table->index(['connection_id', 'parent_user'], 'dra_conn_parent_idx');
        });

        Schema::create('domain_projections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('connection_id')->constrained('domain_provider_connections', indexName: 'dp_connection_fk')->cascadeOnDelete();

            $table->string('external_domain', 253);
            $table->char('domain_hash', 64); // sha256(lower(external_domain)) — Unique/Lookup
            $table->string('external_user', 190);
            $table->foreignId('reseller_account_id')->nullable()->constrained('domain_reseller_accounts', indexName: 'dp_reseller_fk')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers', indexName: 'dp_customer_fk')->nullOnDelete();

            $table->string('registrar', 190)->nullable();
            $table->string('status', 64)->nullable();       // Provider-Domainstatus
            $table->string('sync_status', 16)->default('stale'); // current|stale|pending|conflict|unknown
            $table->string('renewal_mode', 16)->nullable();
            $table->string('next_action', 190)->nullable();
            $table->boolean('transferlock')->nullable();

            $table->date('registration_at')->nullable();
            $table->date('expiration_at')->nullable();
            $table->date('accounting_at')->nullable();      // paiddate
            $table->date('failure_at')->nullable();
            $table->date('finalization_at')->nullable();

            $table->decimal('renewal_price', 12, 2)->nullable();
            $table->string('renewal_currency', 3)->nullable();

            $table->string('revision', 190)->nullable();
            $table->string('raw_hash', 64)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'connection_id', 'domain_hash'], 'dp_org_conn_domainhash_uq');
            $table->index(['organization_id', 'expiration_at'], 'dp_org_expiry_idx');
            $table->index(['organization_id', 'customer_id'], 'dp_org_customer_idx');
            $table->index(['organization_id', 'sync_status'], 'dp_org_sync_idx');
        });

        Schema::create('domain_contact_projections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('connection_id')->constrained('domain_provider_connections', indexName: 'dcp_connection_fk')->cascadeOnDelete();

            $table->string('external_handle', 190);
            $table->string('external_user', 190)->nullable();
            // Minimierter, redigierter Kontaktsnapshot (keine vollständige PII).
            $table->json('snapshot')->nullable();
            $table->string('revision', 190)->nullable();
            $table->string('raw_hash', 64)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'connection_id', 'external_handle'], 'dcp_org_conn_handle_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('domain_contact_projections');
        Schema::dropIfExists('domain_projections');
        Schema::dropIfExists('domain_reseller_accounts');
    }
};
