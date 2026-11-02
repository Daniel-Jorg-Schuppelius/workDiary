<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_02_100000_add_paid_date_checked_at_to_lexoffice_vouchers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Prüfmarke des Zahlungsdatums (Leistungsanalyse 2026-11-02, perf-06): Belege ohne
// Ergebnis werden erst nach Frist erneut bei Lexoffice gefragt.
return new class extends Migration {
    public function up(): void {
        Schema::table('lexoffice_vouchers', function (Blueprint $table): void {
            $table->timestamp('paid_date_checked_at')->nullable()->after('paid_date');
        });
    }

    public function down(): void {
        Schema::table('lexoffice_vouchers', function (Blueprint $table): void {
            $table->dropColumn('paid_date_checked_at');
        });
    }
};
