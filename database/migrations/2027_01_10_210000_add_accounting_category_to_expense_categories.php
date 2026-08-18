<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_10_210000_add_accounting_category_to_expense_categories.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Buchungskategorie je Auslagenkategorie (Feature 106): die ID der Kategorie
 * im führenden Buchhaltungssystem (Lexoffice-Referenz: Kategorie-UUID der
 * Vouchers-API). Ohne Zuordnung kein Push — Fehlermeldung statt Rateweg.
 *
 * Am Stamm (org-gebunden über expense_categories) statt in einer eigenen
 * Mapping-Tabelle: Es gibt je Organisation genau EIN führendes
 * Buchhaltungssystem (Nicht-Ziel: paralleler Push in mehrere).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('expense_categories', function (Blueprint $table): void {
            $table->string('accounting_category_id', 64)->nullable()->after('is_active');
        });
    }

    public function down(): void {
        Schema::table('expense_categories', function (Blueprint $table): void {
            $table->dropColumn('accounting_category_id');
        });
    }
};
