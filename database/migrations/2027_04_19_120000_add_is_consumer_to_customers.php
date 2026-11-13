<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_19_120000_add_is_consumer_to_customers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1688: Merkmal „Verbraucher“ am Kunden (Leistungsort nach § 3a UStG).
 * Bestandskunden gelten als Unternehmen, ihr Steuerfall bleibt.
 */
return new class extends Migration {
    public function up(): void {
        if (Schema::hasColumn('customers', 'is_consumer')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table): void {
            $table->boolean('is_consumer')->default(false)->after('is_reverse_charge_recipient');
        });
    }

    public function down(): void {
        if (! Schema::hasColumn('customers', 'is_consumer')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('is_consumer');
        });
    }
};
