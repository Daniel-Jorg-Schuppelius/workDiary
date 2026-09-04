<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_20_100000_create_resale_subscriptions_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 152 (MVP-758): Reselling-Register — je weiterverkaufte wiederkehrende
 * Leistung (Lizenz, Domain, Hosting …) ein Abo mit genau einem Halter: Kunde,
 * Fremdkunde (Endkunde eines Partners) oder eigener Bestand.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('resale_subscriptions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 16);                          // license|domain|hosting|mailbox|backup|other
            $t->string('provider', 32);                      // telekom_marketplace|qualityhosting|domainreselling|manual|other
            $t->string('external_id', 120)->nullable();      // Anbieter-Kennung (Entitlement, Vertrag, Domain)
            $t->string('external_order_id', 120)->nullable();
            $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $t->foreignId('foreign_customer_id')->nullable()->constrained('foreign_customers')->nullOnDelete();
            $t->boolean('is_own_holding')->default(false);   // eigener Bestand: nie fakturiert
            $t->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $t->string('label', 190);                        // Produktbezeichnung, wie der Anbieter sie nennt
            $t->unsignedInteger('quantity')->default(1);
            $t->date('starts_on');
            $t->date('ends_on')->nullable();                 // offen = verlängert sich
            $t->unsignedSmallInteger('term_months')->default(12);
            $t->string('interval', 8)->default('yearly');    // yearly|monthly (BillingFrequency)
            $t->string('renewal', 8)->default('auto');       // auto|cancel
            $t->decimal('purchase_unit_price', 12, 4)->nullable(); // Einkauf je Stück und Intervall
            $t->decimal('sale_unit_price', 12, 4)->nullable();     // Verkauf je Stück und Intervall
            $t->char('currency', 3)->default('EUR');
            $t->string('status', 16)->default('active');     // active|cancelled|superseded|ended
            $t->foreignId('successor_id')->nullable()->constrained('resale_subscriptions')->nullOnDelete();
            $t->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $t->foreignId('domain_projection_id')->nullable()->constrained('domain_projections')->nullOnDelete();
            $t->string('raw_hash', 64)->nullable();
            $t->string('sync_status', 16)->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->unique(['organization_id', 'provider', 'external_id'], 'resale_subs_org_provider_ext_uq');
            $t->index(['organization_id', 'status', 'kind'], 'resale_subs_org_status_kind_idx');
            $t->index(['organization_id', 'customer_id'], 'resale_subs_org_customer_idx');
            $t->index(['organization_id', 'foreign_customer_id'], 'resale_subs_org_foreign_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('resale_subscriptions');
    }
};
