<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_22_100200_add_correction_to_expenses.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Korrektur übergebener Auslagen (MVP-802, Feature 106): Nach dem Push ist der
 * Beleg im Zielsystem unveränderlich. Korrigiert wird per Gegenbeleg; die neue
 * Auslage zeigt auf die ursprüngliche — Muster `billing_transfers.corrects_transfer_id`
 * (MVP-490).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('expenses', function (Blueprint $table): void {
            if ($this->isSqlite()) {
                // Kein Fremdschlüssel unter SQLite: er erzwingt einen Neuaufbau der
                // Tabelle, der Teilindizes verlieren kann (siehe MVP-800).
                $table->unsignedBigInteger('corrects_expense_id')->nullable();
                $table->index('corrects_expense_id', 'expenses_corrects_idx');
            } else {
                $table->foreignId('corrects_expense_id')->nullable()->after('reimbursement_reference')
                    ->constrained('expenses', indexName: 'expenses_corrects_fk')->nullOnDelete();
            }
            $table->string('correction_reason', 500)->nullable();
        });
    }

    public function down(): void {
        Schema::table('expenses', function (Blueprint $table): void {
            if ($this->isSqlite()) {
                $table->dropIndex('expenses_corrects_idx');
            } else {
                $table->dropForeign('expenses_corrects_fk');
            }
            $table->dropColumn(['corrects_expense_id', 'correction_reason']);
        });
    }

    private function isSqlite(): bool {
        return Schema::getConnection()->getDriverName() === 'sqlite';
    }
};
