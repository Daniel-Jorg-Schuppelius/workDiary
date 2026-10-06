<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_10_100700_add_deferred_from_status_to_investment_cases.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Konsolidierungs-Audit 2026-10, vierte Entscheidungsrunde: Zurückstellen
 * merkt sich die Planungsphase, in die „Wieder aufnehmen“ zurückführt.
 * Altbestand ohne Wert landet wieder in der Idee.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('investment_cases', function (Blueprint $table): void {
            $table->string('deferred_from_status', 32)->nullable()->after('status');
        });
    }

    public function down(): void {
        Schema::table('investment_cases', function (Blueprint $table): void {
            $table->dropColumn('deferred_from_status');
        });
    }
};
