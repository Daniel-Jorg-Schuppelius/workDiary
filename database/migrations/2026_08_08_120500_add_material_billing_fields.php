<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_08_120500_add_material_billing_fields.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Material-Abrechnung:
 * - material_usages.billed: bereits auf einer Materialrechnung erfasst (Sperre
 *   gegen Doppelberechnung, analog zu time_entries.exported).
 * - invoice_items.material_usage_id: Verknüpfung der Materialposition zur
 *   zugrunde liegenden Materialverwendung.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('material_usages', function (Blueprint $table): void {
            $table->boolean('billed')->default(false)->after('line_total_net');
            $table->index('billed');
        });

        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->foreignId('material_usage_id')->nullable()->after('expense_id')
                ->constrained('material_usages')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('material_usage_id');
        });

        Schema::table('material_usages', function (Blueprint $table): void {
            $table->dropIndex(['billed']);
            $table->dropColumn('billed');
        });
    }
};
