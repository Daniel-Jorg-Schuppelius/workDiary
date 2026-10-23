<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_31_160000_add_bid_comment_permission_to_bill_of_quantities_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freischaltung der Bieterkommentare (MVP-1298, `PrjInfo/BidCommPerm`): der
 * Auftraggeber legt sie in der Angebotsaufforderung fest; null = die Datei sagt
 * nichts.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('bill_of_quantities', function (Blueprint $table): void {
            $table->boolean('is_bid_comment_permitted')->nullable()->after('preamble');
        });
    }

    public function down(): void {
        Schema::table('bill_of_quantities', function (Blueprint $table): void {
            $table->dropColumn('is_bid_comment_permitted');
        });
    }
};
