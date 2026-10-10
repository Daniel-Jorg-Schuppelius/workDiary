<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_16_100300_add_document_to_invoice_commissions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Models\Invoicing\Invoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * MVP-1119 (E14): Provisionen auch an gespiegelten Rechnungen des
 * Buchhaltungsprogramms. Die Zeile hängt über den Morph `document` am Beleg;
 * `invoice_id` bleibt für lokale Rechnungen. Belegnummer und Kunde werden bei
 * Entstehung festgehalten, damit Liste und Lohnexport keine Quelle fragen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('invoice_commissions', function (Blueprint $t): void {
            $t->unsignedBigInteger('invoice_id')->nullable()->change();
            $t->string('document_type', 64)->nullable()->after('invoice_id');
            $t->unsignedBigInteger('document_id')->nullable()->after('document_type');
            $t->string('document_number', 64)->nullable()->after('document_id');
            $t->foreignId('customer_id')->nullable()->after('document_number')->constrained('customers')->nullOnDelete();
            $t->index(['document_type', 'document_id'], 'inv_comm_document_idx');
        });

        DB::table('invoice_commissions')->whereNotNull('invoice_id')->update([
            'document_type' => (new Invoice)->getMorphClass(),
            'document_id' => DB::raw('invoice_id'),
            'document_number' => DB::raw('(SELECT invoices.number FROM invoices WHERE invoices.id = invoice_commissions.invoice_id)'),
            'customer_id' => DB::raw('(SELECT invoices.customer_id FROM invoices WHERE invoices.id = invoice_commissions.invoice_id)'),
        ]);
    }

    public function down(): void {
        Schema::table('invoice_commissions', function (Blueprint $t): void {
            $t->dropIndex('inv_comm_document_idx');
            $t->dropConstrainedForeignId('customer_id');
            $t->dropColumn(['document_type', 'document_id', 'document_number']);
        });
    }
};
