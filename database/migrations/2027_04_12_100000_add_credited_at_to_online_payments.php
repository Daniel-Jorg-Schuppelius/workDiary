<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_12_100000_add_credited_at_to_online_payments.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1523: Führt ein Buchhaltungsprogramm die Fakturierung, entsteht für die
 * Verleih-Vorauszahlung keine lokale Rechnung. Der Zeitpunkt, zu dem sie bei
 * der Übergabe angerechnet wurde, ersetzt dann die Rechnungszuordnung.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('online_payments', function (Blueprint $table): void {
            $table->timestamp('credited_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void {
        Schema::table('online_payments', function (Blueprint $table): void {
            $table->dropColumn('credited_at');
        });
    }
};
