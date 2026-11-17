<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_25_100000_add_property_country_to_invoices.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1706: Land des Grundstücks an der Rechnung (§ 3a Abs. 3 Nr. 1 UStG).
 * Leer = Einsatzort des Auftrags bzw. Inland; Bestandsrechnungen bleiben leer.
 */
return new class extends Migration {
    public function up(): void {
        if (Schema::hasColumn('invoices', 'property_country')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table): void {
            $table->char('property_country', 2)->nullable()->after('is_reverse_charge');
        });
    }

    public function down(): void {
        if (! Schema::hasColumn('invoices', 'property_country')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('property_country');
        });
    }
};
