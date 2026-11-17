<?php
/*
 * Created on   : Tue Nov 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_26_120000_add_stock_delivery_to_claim_rma_returns.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Auslieferung, auf die sich eine Rücksendung bezieht (MVP-1737): Stückwert der Wiedereinlagerung auch ohne Seriennummer. */
return new class extends Migration {
    public function up(): void {
        if (Schema::hasColumn('claim_rma_returns', 'stock_delivery_id')) {
            return;
        }
        Schema::table('claim_rma_returns', function (Blueprint $t): void {
            if ($this->isSqlite()) {
                // Ein Fremdschlüssel zwingt SQLite zum Neuaufbau der Tabelle; die Integrität sichern MySQL/MariaDB.
                $t->unsignedBigInteger('stock_delivery_id')->nullable();
                $t->index('stock_delivery_id', 'crr_stock_delivery_idx');

                return;
            }
            $t->foreignId('stock_delivery_id')->nullable()->after('stock_lot_id')
                ->constrained('stock_deliveries', indexName: 'crr_stock_delivery_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        if (! Schema::hasColumn('claim_rma_returns', 'stock_delivery_id')) {
            return;
        }
        Schema::table('claim_rma_returns', function (Blueprint $t): void {
            if ($this->isSqlite()) {
                $t->dropIndex('crr_stock_delivery_idx');
            } else {
                $t->dropForeign('crr_stock_delivery_fk');
            }
            $t->dropColumn('stock_delivery_id');
        });
    }

    private function isSqlite(): bool {
        return Schema::getConnection()->getDriverName() === 'sqlite';
    }
};
