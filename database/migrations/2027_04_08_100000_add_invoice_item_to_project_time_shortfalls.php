<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_08_100000_add_invoice_item_to_project_time_shortfalls.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Auffüllung auf das Projekt-Soll in der lokalen Rechnung (Feature 194, MVP-1494): Rechnungsposition am Befund. */
return new class extends Migration {
    public function up(): void {
        Schema::table('project_time_shortfalls', function (Blueprint $t): void {
            if ($this->isSqlite()) {
                // Ein Fremdschlüssel zwingt SQLite zum Neuaufbau der Tabelle; die Integrität sichern MySQL/MariaDB.
                $t->unsignedBigInteger('invoice_item_id')->nullable();
                $t->index('invoice_item_id', 'pts_invoice_item_idx');

                return;
            }
            $t->foreignId('invoice_item_id')->nullable()->after('billing_transfer_position_id')
                ->constrained('invoice_items', indexName: 'pts_invoice_item_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('project_time_shortfalls', function (Blueprint $t): void {
            if ($this->isSqlite()) {
                $t->dropIndex('pts_invoice_item_idx');
            } else {
                $t->dropForeign('pts_invoice_item_fk');
            }
            $t->dropColumn('invoice_item_id');
        });
    }

    private function isSqlite(): bool {
        return Schema::getConnection()->getDriverName() === 'sqlite';
    }
};
