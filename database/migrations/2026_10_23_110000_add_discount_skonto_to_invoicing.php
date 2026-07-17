<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_23_110000_add_discount_skonto_to_invoicing.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// MVP-416: Positions-/Belegrabatt + Skonto-Konditionen (EN-16931-Allowances, BT-20-Skonto).
return new class extends Migration {
    public function up(): void {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->decimal('discount_percent', 5, 2)->nullable()->after('unit_price');
            $table->decimal('discount_amount', 12, 2)->nullable()->after('discount_percent');
        });

        Schema::table('quote_items', function (Blueprint $table): void {
            $table->decimal('discount_percent', 5, 2)->nullable()->after('unit_price');
            $table->decimal('discount_amount', 12, 2)->nullable()->after('discount_percent');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->decimal('discount_percent', 5, 2)->nullable()->after('subtotal');
            $table->decimal('discount_amount', 12, 2)->nullable()->after('discount_percent');
            $table->decimal('skonto_percent', 5, 2)->nullable()->after('payment_terms_days');
            $table->unsignedSmallInteger('skonto_days')->nullable()->after('skonto_percent');
        });
    }

    public function down(): void {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->dropColumn(['discount_percent', 'discount_amount']);
        });
        Schema::table('quote_items', function (Blueprint $table): void {
            $table->dropColumn(['discount_percent', 'discount_amount']);
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['discount_percent', 'discount_amount', 'skonto_percent', 'skonto_days']);
        });
    }
};
