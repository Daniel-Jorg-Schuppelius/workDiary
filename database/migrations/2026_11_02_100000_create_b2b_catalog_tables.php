<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_11_02_100000_create_b2b_catalog_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B2B-Katalogzugang (Feature 099, MVP-457/458): Punchout-Zugänge je Kunde
 * (Secret nur als SHA-256-Hash, Muster scim_tokens), freigegebener
 * Katalogausschnitt mit optionalem Kundenpreis und die Spiegeltabelle der
 * eingegangenen openTRANS-Bestellungen (Idempotenz: ORDER-ID + Käufer).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('b2b_catalog_accesses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'b2bacc_org_fk')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers', indexName: 'b2bacc_customer_fk')->cascadeOnDelete();
            $table->string('label', 120);
            $table->string('username', 64);
            $table->string('secret_hash', 64);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'b2bacc_creator_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'username'], 'b2bacc_org_username_unique');
            $table->index(['organization_id', 'customer_id'], 'b2bacc_org_customer_idx');
        });

        Schema::create('b2b_catalog_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'b2bcat_org_fk')->cascadeOnDelete();
            $table->foreignId('access_id')->constrained('b2b_catalog_accesses', indexName: 'b2bcat_access_fk')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('articles', indexName: 'b2bcat_article_fk')->cascadeOnDelete();
            // Kundenindividueller Preis; NULL = Standard-Verkaufspreis des Artikels.
            $table->decimal('custom_price', 13, 4)->nullable();
            $table->timestamps();

            $table->unique(['access_id', 'article_id'], 'b2bcat_access_article_unique');
            $table->index('organization_id', 'b2bcat_org_idx');
        });

        Schema::create('b2b_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'b2bord_org_fk')->cascadeOnDelete();
            $table->foreignId('access_id')->nullable()->constrained('b2b_catalog_accesses', indexName: 'b2bord_access_fk')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers', indexName: 'b2bord_customer_fk')->nullOnDelete();
            $table->string('external_order_id', 120);
            // Normalisierter Käuferschlüssel (USt-IdNr., sonst Name) für die
            // Idempotenz, solange kein Kunde zugeordnet ist.
            $table->string('buyer_key', 64);
            $table->json('buyer')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->decimal('total_net', 13, 2)->nullable();
            $table->json('lines');
            $table->string('source', 32);
            $table->string('status', 16)->default('open');
            $table->timestamp('ordered_at')->nullable();
            $table->date('requested_delivery_date')->nullable();
            $table->foreignId('diary_entry_id')->nullable()->constrained('diary_entries', indexName: 'b2bord_diary_fk')->nullOnDelete();
            $table->foreignId('booked_by')->nullable()->constrained('users', indexName: 'b2bord_booker_fk')->nullOnDelete();
            $table->timestamp('booked_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'external_order_id', 'buyer_key'], 'b2bord_org_order_buyer_unique');
            $table->index(['organization_id', 'status'], 'b2bord_org_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('b2b_orders');
        Schema::dropIfExists('b2b_catalog_items');
        Schema::dropIfExists('b2b_catalog_accesses');
    }
};
