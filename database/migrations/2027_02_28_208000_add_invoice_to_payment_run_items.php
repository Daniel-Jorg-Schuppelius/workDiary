<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_208000_add_invoice_to_payment_run_items.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-1011: Belegbezug der Lastschrift, damit die Liquiditätsvorschau die Forderung nicht doppelt zählt. */
return new class extends Migration {
    public function up(): void {
        Schema::table('payment_run_items', function (Blueprint $table): void {
            $table->foreignId('invoice_id')->nullable()->after('incoming_invoice_retention_id')
                ->constrained('invoices', indexName: 'payment_run_items_invoice_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('payment_run_items', function (Blueprint $table): void {
            $table->dropForeign('payment_run_items_invoice_fk');
            $table->dropColumn('invoice_id');
        });
    }
};
