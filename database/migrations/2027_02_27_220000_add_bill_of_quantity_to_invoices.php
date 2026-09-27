<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_27_220000_add_bill_of_quantity_to_invoices.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-932: Rechnungen je Leistungsverzeichnis (Abschläge aus dem Leistungsstand, Abrufe). */
return new class extends Migration {
    public function up(): void {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('bill_of_quantity_id')->nullable()->after('project_id')
                ->constrained('bill_of_quantities', indexName: 'invoices_boq_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign('invoices_boq_fk');
            $table->dropColumn('bill_of_quantity_id');
        });
    }
};
