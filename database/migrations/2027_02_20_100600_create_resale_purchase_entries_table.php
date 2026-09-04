<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_20_100600_create_resale_purchase_entries_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 152 (MVP-762): Einkaufsbelege — was der Anbieter tatsächlich
 * berechnet hat, je Periode zugeteilt: aus gespiegelten Eingangsrechnungen
 * (Marketplace-Anteil, pro rata auf die laufenden Abos des Monats), aus dem
 * Domain-Buchungsjournal (083) oder von Hand.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('resale_purchase_entries', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('subscription_id')->nullable()->constrained('resale_subscriptions')->cascadeOnDelete();
            $t->foreignId('period_id')->nullable()->constrained('resale_periods')->nullOnDelete();
            $t->string('provider', 32);
            $t->string('source', 24);                        // voucher|domain_accounting|manual
            $t->foreignId('lexoffice_voucher_id')->nullable()->constrained('lexoffice_vouchers')->nullOnDelete();
            $t->foreignId('domain_accounting_entry_id')->nullable()->constrained('domain_accounting_entries')->nullOnDelete();
            $t->string('document_number', 64)->nullable();
            $t->date('entry_date');
            $t->string('description', 255)->nullable();
            $t->decimal('net_amount', 12, 2);
            $t->char('currency', 3)->default('EUR');
            $t->string('raw_hash', 64);
            $t->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->unique(['organization_id', 'raw_hash'], 'resale_purchases_org_hash_uq');
            $t->index(['organization_id', 'provider', 'entry_date'], 'resale_purchases_org_prov_date_idx');
            $t->index(['period_id'], 'resale_purchases_period_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('resale_purchase_entries');
    }
};
