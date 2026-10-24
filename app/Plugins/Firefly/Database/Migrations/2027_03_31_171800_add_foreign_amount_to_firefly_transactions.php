<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_31_171800_add_foreign_amount_to_firefly_transactions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Firefly III (Feature 176): Betrag in der zweiten Währung einer Buchung
 * (`foreign_amount`, `foreign_currency_code`).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('firefly_transactions', function (Blueprint $table): void {
            if (! Schema::hasColumn('firefly_transactions', 'foreign_amount')) {
                $table->decimal('foreign_amount', 15, 2)->nullable()->after('currency');
                $table->string('foreign_currency', 3)->nullable()->after('foreign_amount');
            }
        });
    }

    public function down(): void {
        Schema::table('firefly_transactions', function (Blueprint $table): void {
            $table->dropColumn(['foreign_amount', 'foreign_currency']);
        });
    }
};
