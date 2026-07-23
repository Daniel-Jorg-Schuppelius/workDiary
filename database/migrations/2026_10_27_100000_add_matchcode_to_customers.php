<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_27_100000_add_matchcode_to_customers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kunden-Kürzel (Matchcode) für die Fernwartungs-Inbox: exakter Abgleich
 * gegen Alias-Tokens (z. B. „GSL" in „GSL-DC01") beim Zuweisungsvorschlag.
 * Wird beim Zuweisen optional mitgepflegt, kein Pflichtfeld.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('matchcode', 16)->nullable()->after('number');
        });
    }

    public function down(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('matchcode');
        });
    }
};
