<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_31_150000_add_preamble_to_bill_of_quantities_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vorbemerkungen des Leistungsverzeichnisses (MVP-1303): die Vertragstexte vor
 * den Positionen, ein Text je Block, so wie die GAEB-Datei sie liefert.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('bill_of_quantities', function (Blueprint $table): void {
            $table->json('preamble')->nullable()->after('totals');
        });
    }

    public function down(): void {
        Schema::table('bill_of_quantities', function (Blueprint $table): void {
            $table->dropColumn('preamble');
        });
    }
};
