<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_31_171900_add_source_deletion_and_foreign_amount_to_accounting_vouchers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Belegspiegel (Feature 122/176): Zeitpunkt, zu dem der Abgleich den Beleg im
 * Fremdsystem nicht mehr fand, und der Betrag in einer zweiten Währung (etwa der
 * Firmen-Gegenwert einer Ausgabe in Fremdwährung).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('accounting_vouchers', function (Blueprint $table): void {
            if (! Schema::hasColumn('accounting_vouchers', 'source_deleted_at')) {
                $table->timestamp('source_deleted_at')->nullable()->after('source_changed_at');
            }
            if (! Schema::hasColumn('accounting_vouchers', 'foreign_amount')) {
                $table->decimal('foreign_amount', 15, 2)->nullable()->after('currency');
                $table->string('foreign_currency', 3)->nullable()->after('foreign_amount');
            }
        });
    }

    public function down(): void {
        Schema::table('accounting_vouchers', function (Blueprint $table): void {
            $table->dropColumn(['source_deleted_at', 'foreign_amount', 'foreign_currency']);
        });
    }
};
