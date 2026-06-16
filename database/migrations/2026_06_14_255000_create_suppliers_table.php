<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_05_120000_create_suppliers_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lieferanten (Suppliers): Geschäftspartner, von denen wir Waren/Leistungen
 * beziehen. Spiegelt das Kunden-Stammdatenmodell, jedoch ohne Abrechnungs-,
 * Stundensatz- und Rechnungstext-Felder (ein Lieferant verkauft an uns).
 * Ergänzt um `vendor_number` (Lexoffice-Lieferantennummer) und ein
 * `active`-Flag. Mappt im Lexoffice-Kontakt-Sync auf `roles.vendor`.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('number', 64)->nullable();
            $table->string('vendor_number', 64)->nullable();
            $table->string('company', 200)->nullable();
            $table->string('vat_id', 64)->nullable();
            $table->string('contact_name', 200)->nullable();
            $table->json('contact_persons')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('mobile', 64)->nullable();
            $table->string('fax', 64)->nullable();
            $table->string('homepage')->nullable();
            $table->text('address')->nullable();
            $table->string('address_street', 255)->nullable();
            $table->string('address_zip', 32)->nullable();
            $table->string('address_city', 128)->nullable();
            $table->decimal('address_lat', 10, 7)->nullable();
            $table->decimal('address_lng', 10, 7)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->string('timezone', 64)->nullable();
            $table->string('color', 16)->nullable();
            $table->text('comment')->nullable();
            $table->string('bank_account_holder', 200)->nullable();
            $table->string('bank_iban', 64)->nullable();
            $table->string('bank_bic', 32)->nullable();
            $table->string('bank_name', 200)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('archived_at');
            $table->index('name', 'suppliers_name_idx');
            $table->unique(['organization_id', 'number']);
            $table->unique(['organization_id', 'slug'], 'suppliers_org_slug_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('suppliers');
    }
};
