<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_102500_add_cost_center_dimension_and_accounting_budgets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kostenstellen-Dimension und Konto-Budgets (Feature 142, MVP-709).
 *
 *  - `accounting_entry_lines.cost_center_id`: Analysebezug wie `project_id`/
 *    `asset_id` (SET NULL) — kein Buchungsinhalt, die Festschreibung bleibt
 *    am Betrag/Konto/Datum.
 *  - `accounting_accounts.bwa_group`: ausdrückliche BWA-Zuordnung je Konto;
 *    fehlt sie, leitet der Bericht die Gruppe aus dem SKR-Nummernkreis ab.
 *  - `accounting_budgets`: Planwert je Konto × Geschäftsjahr, wahlweise je
 *    Kostenstelle und je Monat. Unique-Entscheidung: MySQL/MariaDB zählen
 *    NULL in Unique-Indizes als „verschieden" — zwei Jahreswerte ohne
 *    Kostenstelle wären damit erlaubt. Deshalb sind `month` (0 = Jahreswert)
 *    und die Spiegelspalte `cost_center_key` (0 = ohne Kostenstelle, sonst
 *    = cost_center_id; vom Modell gepflegt) NOT NULL. Eine generierte Spalte
 *    schied aus: Beim SET NULL der Kostenstelle würde sie auf 0 fallen und
 *    könnte mit einem vorhandenen Budget ohne Kostenstelle kollidieren —
 *    das Löschen der Kostenstelle schlüge dann fehl. Die Spiegelspalte
 *    behält ihren historischen Wert; IDs werden nicht wiederverwendet.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('accounting_entry_lines', function (Blueprint $table): void {
            $table->unsignedBigInteger('cost_center_id')->nullable()->after('asset_id');
            $table->index(['organization_id', 'cost_center_id'], 'acc_line_org_cc_idx');
            $table->foreign('cost_center_id', 'acc_line_cc_fk')
                ->references('id')->on('cost_centers')->nullOnDelete();
        });

        Schema::table('accounting_accounts', function (Blueprint $table): void {
            $table->string('bwa_group', 32)->nullable()->after('euer_category');
        });

        Schema::create('accounting_budgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedBigInteger('accounting_account_id');
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->unsignedBigInteger('cost_center_key')->default(0);
            // 0 = Jahreswert, 1–12 = Kalendermonat.
            $table->unsignedTinyInteger('month')->default(0);
            $table->decimal('amount', 18, 2)->default(0);
            $table->char('currency', 3)->default('EUR');
            $table->string('note', 191)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'fiscal_year', 'accounting_account_id', 'cost_center_key', 'month'], 'acc_budgets_unique');
            $table->index(['organization_id', 'fiscal_year'], 'acc_budgets_org_year_idx');

            $table->foreign('accounting_account_id', 'acc_budget_account_fk')
                ->references('id')->on('accounting_accounts')->cascadeOnDelete();
            $table->foreign('cost_center_id', 'acc_budget_cc_fk')
                ->references('id')->on('cost_centers')->nullOnDelete();
            $table->foreign('created_by', 'acc_budget_creator_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::dropIfExists('accounting_budgets');

        Schema::table('accounting_accounts', function (Blueprint $table): void {
            $table->dropColumn('bwa_group');
        });

        Schema::table('accounting_entry_lines', function (Blueprint $table): void {
            $table->dropForeign('acc_line_cc_fk');
            $table->dropIndex('acc_line_org_cc_idx');
            $table->dropColumn('cost_center_id');
        });
    }
};
