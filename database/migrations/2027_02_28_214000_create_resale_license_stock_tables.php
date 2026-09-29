<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_214000_create_resale_license_stock_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-1024: Lizenzbestand aus Einkaufspaketen — Produkt, Paket, Lizenz, Schlüssel, Verkauf. */
return new class extends Migration {
    public function up(): void {
        Schema::create('resale_license_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('manufacturer', 120)->nullable();
            $table->json('key_roles');
            $table->unsignedInteger('reorder_level')->nullable();
            // Katalogschlüssel (`art:<id>`, `lex:<id>`, MVP-1025) — kein anbieterbezogener Fremdschlüssel.
            $table->string('article_ref', 80)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'name'], 'resale_lic_products_org_name_uq');
        });

        Schema::create('resale_license_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('resale_license_products')->restrictOnDelete();
            $table->string('reference', 80);
            $table->date('purchased_on');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('supplier_name', 160)->nullable();
            $table->unsignedInteger('quantity');
            // Beim Kauf eingefroren: Änderungen am Produkt wirken erst auf neue Pakete.
            $table->json('key_roles');
            $table->unsignedSmallInteger('key_count');
            $table->string('document_type', 64)->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->string('document_reference', 120)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'reference'], 'resale_lic_batches_org_ref_uq');
            $table->index(['organization_id', 'product_id'], 'resale_lic_batches_org_product_idx');
        });

        Schema::create('resale_license_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('resale_license_batches')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->timestamp('blocked_at')->nullable();
            $table->string('blocked_reason', 255)->nullable();
            $table->foreignId('blocked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'batch_id', 'position'], 'resale_lic_units_org_batch_pos_uq');
        });

        Schema::create('resale_license_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('resale_license_units')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('resale_license_products')->restrictOnDelete();
            $table->string('role', 32);
            $table->text('value');
            $table->char('fingerprint', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['unit_id', 'role'], 'resale_lic_keys_unit_role_uq');
            $table->unique(['organization_id', 'product_id', 'role', 'fingerprint'], 'resale_lic_keys_dup_uq');
        });

        Schema::create('resale_license_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('resale_license_units')->cascadeOnDelete();
            // Gesetzt, solange die Zuordnung gilt: der Unique-Index erlaubt je Lizenz
            // genau eine aktive Zuordnung (NULL mehrfach — MySQL wie SQLite).
            $table->foreignId('active_unit_id')->nullable()->unique('resale_lic_assign_active_uq')
                ->constrained('resale_license_units', indexName: 'resale_lic_assign_active_fk')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('foreign_customer_id')->nullable()->constrained('foreign_customers')->nullOnDelete();
            $table->date('sold_on');
            $table->string('invoice_reference', 80)->nullable();
            $table->char('request_token', 32)->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('end_kind', 16)->nullable();
            $table->string('end_reason', 255)->nullable();
            $table->foreignId('ended_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'request_token'], 'resale_lic_assign_token_uq');
            $table->index(['organization_id', 'customer_id'], 'resale_lic_assign_org_customer_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('resale_license_assignments');
        Schema::dropIfExists('resale_license_keys');
        Schema::dropIfExists('resale_license_units');
        Schema::dropIfExists('resale_license_batches');
        Schema::dropIfExists('resale_license_products');
    }
};
