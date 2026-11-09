<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_11_100100_add_archived_outcome_to_application_opportunities.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1517: Eine entschiedene Ausschreibungsakte lässt sich ablegen. Die
 * Entscheidung (gewonnen, verloren, zurückgezogen) bleibt dabei für
 * Trefferquote und Verlustgründe erhalten.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('application_opportunities', function (Blueprint $table): void {
            $table->string('archived_outcome', 20)->nullable()->after('status');
        });
    }

    public function down(): void {
        Schema::table('application_opportunities', function (Blueprint $table): void {
            $table->dropColumn('archived_outcome');
        });
    }
};
