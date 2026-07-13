<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_13_100000_add_finance_format_and_datev_rest_columns.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-334 (Bauturbo A15): Finanzformat-Import + DATEV-Rest.
 *
 *  - bank_transactions.return_reason: ISO-20022-Rückgabegrund (RtrInf/Rsn/Cd,
 *    z. B. AC04/MD06) für den Lastschrift-Rückläufer-Workflow. Reiner Code,
 *    KEINE PII ⇒ plaintext.
 *  - datev_booking_sources.is_reversal: Storno-Übergabe — der Buchungssatz
 *    wird mit Generalumkehr-Kennzeichen (EXTF-Feld „Generalumkehr (GU)")
 *    exportiert.
 *  - datev_booking_batches.selection_mode: Persistenz des Zuschnitts am
 *    Exportnachweis (all = kompletter Zeitraum, manual = Teilauswahl).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('bank_transactions', function (Blueprint $table): void {
            $table->string('return_reason', 64)->nullable()->after('is_reversal');
        });

        Schema::table('datev_booking_sources', function (Blueprint $table): void {
            $table->boolean('is_reversal')->default(false)->after('document_ref');
        });

        Schema::table('datev_booking_batches', function (Blueprint $table): void {
            $table->string('selection_mode', 8)->default('all')->after('finalized_locked');
        });
    }

    public function down(): void {
        Schema::table('bank_transactions', function (Blueprint $table): void {
            $table->dropColumn('return_reason');
        });

        Schema::table('datev_booking_sources', function (Blueprint $table): void {
            $table->dropColumn('is_reversal');
        });

        Schema::table('datev_booking_batches', function (Blueprint $table): void {
            $table->dropColumn('selection_mode');
        });
    }
};
