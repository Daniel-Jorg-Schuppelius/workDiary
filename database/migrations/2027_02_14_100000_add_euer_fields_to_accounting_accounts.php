<?php
/*
 * Created on   : Sat Aug 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_14_100000_add_euer_fields_to_accounting_accounts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EÜR-Feinschnitt (Feature 125, MVP-680).
 *
 * Die Zuordnung zur Formularzeile und der abziehbare Anteil hängen am Konto,
 * nicht an der Buchung. `deductible_percent` wirkt ausschließlich in der
 * EÜR-Auswertung — im Journal steht immer der volle Betrag.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('accounting_accounts', function (Blueprint $table): void {
            $table->string('euer_category', 32)->nullable()->after('is_clearing');
            $table->decimal('deductible_percent', 5, 2)->default(100)->after('euer_category');
        });
    }

    public function down(): void {
        Schema::table('accounting_accounts', function (Blueprint $table): void {
            $table->dropColumn(['euer_category', 'deductible_percent']);
        });
    }
};
