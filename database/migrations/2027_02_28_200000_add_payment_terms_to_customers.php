<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_200000_add_payment_terms_to_customers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-996: Zahlungsziel und Skonto als Vorgabe am Kunden. */
return new class extends Migration {
    public function up(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->unsignedSmallInteger('payment_terms_days')->nullable()->after('delivery_format');
            $table->decimal('skonto_percent', 5, 2)->nullable()->after('payment_terms_days');
            $table->unsignedSmallInteger('skonto_days')->nullable()->after('skonto_percent');
        });
    }

    public function down(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn(['payment_terms_days', 'skonto_percent', 'skonto_days']);
        });
    }
};
