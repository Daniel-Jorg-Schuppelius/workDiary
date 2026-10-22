<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_31_140000_add_provisional_and_chain_to_bank_statements_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vorläufige Auszüge (MVP-1274: CAMT.052 Tagesauszug, CAMT.054 Umsatzmeldung)
 * zählen nicht als Buchung, bis der verbindliche Auszug sie ersetzt; die
 * Saldenkette zum Vorgänger-Auszug (MVP-1279) steht je Auszug fest.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('bank_statements', function (Blueprint $table): void {
            $table->boolean('is_provisional')->default(false)->after('source_format');
            $table->timestamp('superseded_at')->nullable()->after('is_provisional');
            $table->string('chain_check', 12)->nullable()->after('balance_check');
        });
    }

    public function down(): void {
        Schema::table('bank_statements', function (Blueprint $table): void {
            $table->dropColumn(['is_provisional', 'superseded_at', 'chain_check']);
        });
    }
};
