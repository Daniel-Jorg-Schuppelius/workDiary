<?php
/*
 * Created on   : Fri Aug 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_102200_add_delivery_format_to_customers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('customers', function (Blueprint $table): void {
            // Kunden-Default für das E-Rechnungs-Ausgabeformat (NULL = PDF).
            $table->string('delivery_format', 24)->nullable()->after('buyer_reference');
        });
    }

    public function down(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('delivery_format');
        });
    }
};
