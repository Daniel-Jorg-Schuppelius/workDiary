<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_02_100000_create_accounting_vouchers_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beleg-Spiegel der Buchhaltungssysteme (Feature 122, MVP-611).
 *
 * Bewusst anbieterneutral mit `plugin_id`: `lexoffice_vouchers` bleibt, wo es
 * ist — dort hängen Zahlungsabgleich und Matching seit Langem. Für jeden
 * weiteren Anbieter eine eigene Tabelle anzulegen, hätte dieselbe Struktur
 * mehrfach ergeben; eine Migration der gewachsenen Lexoffice-Tabelle hätte
 * funktionierenden Code angefasst, ohne dass jemand etwas davon hat.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('accounting_vouchers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('plugin_id', 64);
            $table->string('external_id', 128);
            $table->string('contact_external_id', 128)->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('voucher_type', 32)->nullable();
            $table->string('voucher_status', 32)->nullable();
            $table->string('voucher_number', 64)->nullable();
            $table->date('voucher_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('paid_date')->nullable();
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->decimal('net_amount', 15, 2)->nullable();
            $table->decimal('open_amount', 15, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->boolean('archived')->default(false);
            $table->json('payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'plugin_id', 'external_id'], 'acc_voucher_org_plugin_ext_unique');
            $table->index(['organization_id', 'voucher_date'], 'acc_voucher_org_date_idx');
            $table->index(['organization_id', 'supplier_id'], 'acc_voucher_org_supplier_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('accounting_vouchers');
    }
};
