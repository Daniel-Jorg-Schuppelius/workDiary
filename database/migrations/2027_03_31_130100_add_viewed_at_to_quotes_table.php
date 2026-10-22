<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_31_130100_add_viewed_at_to_quotes_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Angebot „angesehen“ (MVP-1281): erstes Öffnen durch den Kunden über Link oder Portal. */
return new class extends Migration {
    public function up(): void {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->timestamp('viewed_at')->nullable()->after('followed_up_at');
        });
    }

    public function down(): void {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropColumn('viewed_at');
        });
    }
};
