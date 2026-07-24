<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_28_100100_add_customer_billing_rate_id_to_time_entries.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 098: markiert, welcher Sonderkonditions-Satz den hourly_rate-Snapshot
 * erzeugt hat. NULL = normale Hierarchie bzw. manueller Override — nur Einträge
 * MIT gesetztem FK dürfen bei Satzänderungen neu aufgelöst werden.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->foreignId('customer_billing_rate_id')->nullable()
                ->constrained('customer_billing_rates', indexName: 'fk_te_cbr')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_billing_rate_id');
        });
    }
};
