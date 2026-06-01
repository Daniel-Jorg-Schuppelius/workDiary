<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_05_130000_create_lexoffice_vouchers_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lokaler Cache der Lexoffice-Belege (voucherlist) pro verknüpftem Kontakt.
 * Wird per `php artisan lexoffice:sync-vouchers` aktualisiert. Pro Organisation
 * eindeutig über die `external_id` (Lexoffice-UUID des Belegs).
 *
 * Die Belege existieren überwiegend nur in Lexoffice; die Zuordnung zu lokalen
 * Kunden/Lieferanten erfolgt über die Kontakt-ExternalReference.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('lexoffice_vouchers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 64);
            $table->string('contact_external_id', 64)->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('voucher_type', 32)->nullable();   // salesinvoice|purchaseinvoice|creditnote|...
            $table->string('voucher_status', 32)->nullable(); // open|paid|voided|...
            $table->string('voucher_number', 64)->nullable();
            $table->date('voucher_date')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('total_amount', 14, 2)->nullable();
            $table->decimal('open_amount', 14, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->boolean('archived')->default(false);
            $table->json('payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'external_id']);
            $table->index(['organization_id', 'customer_id']);
            $table->index(['organization_id', 'supplier_id']);
            $table->index(['organization_id', 'voucher_type']);
            $table->index('contact_external_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('lexoffice_vouchers');
    }
};
