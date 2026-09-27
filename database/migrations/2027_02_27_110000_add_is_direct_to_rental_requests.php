<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_27_110000_add_is_direct_to_rental_requests.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-916: Direktbuchung aus dem Portal (ohne Entscheidung der Leitung). */
return new class extends Migration {
    public function up(): void {
        Schema::table('rental_requests', function (Blueprint $table): void {
            $table->boolean('is_direct')->default(false)->after('status');
        });
    }

    public function down(): void {
        Schema::table('rental_requests', function (Blueprint $table): void {
            $table->dropColumn('is_direct');
        });
    }
};
