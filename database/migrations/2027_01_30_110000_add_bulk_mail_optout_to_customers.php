<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_30_110000_add_bulk_mail_optout_to_customers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rundschreiben-Opt-out (Feature 119, MVP-608).
 *
 * Bewusst ein eigenes Feld statt einer Wiederverwendung von
 * `exclude_from_reports`: Das eine ist eine Auswertungsentscheidung, das
 * andere eine Kommunikationsentscheidung — sie zusammenzulegen hieße, dass
 * ein aus der Statistik genommener Kunde ungefragt auch keine Post mehr
 * bekommt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->boolean('no_bulk_mail')->default(false)->after('exclude_from_reports');
        });
    }

    public function down(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('no_bulk_mail');
        });
    }
};
