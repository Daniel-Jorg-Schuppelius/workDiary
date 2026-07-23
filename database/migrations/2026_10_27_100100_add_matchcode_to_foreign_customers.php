<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_27_100100_add_matchcode_to_foreign_customers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kürzel (Matchcode) auch für Fremdkunden (Endkunden): Geräte-Aliasse wie
 * „GSL-DC01" benennen oft den Endkunden statt des direkten Kunden — die
 * Fernwartungs-Inbox schlägt dann Kunde UND Endkunde vor.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('foreign_customers', function (Blueprint $table): void {
            $table->string('matchcode', 16)->nullable()->after('number');
        });
    }

    public function down(): void {
        Schema::table('foreign_customers', function (Blueprint $table): void {
            $table->dropColumn('matchcode');
        });
    }
};
