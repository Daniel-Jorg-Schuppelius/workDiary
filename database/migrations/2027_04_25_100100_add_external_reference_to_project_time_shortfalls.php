<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_25_100100_add_external_reference_to_project_time_shortfalls.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Soll-Befund, den ein Zeitbeleg im Buchhaltungsprogramm mit der Pauschale abrechnet (MVP-1716): Verweis auf die Fremdreferenz des Belegs. */
return new class extends Migration {
    public function up(): void {
        if (Schema::hasColumn('project_time_shortfalls', 'external_reference_id')) {
            return;
        }
        Schema::table('project_time_shortfalls', function (Blueprint $t): void {
            if ($this->isSqlite()) {
                // Ein Fremdschlüssel zwingt SQLite zum Neuaufbau der Tabelle; die Integrität sichern MySQL/MariaDB.
                $t->unsignedBigInteger('external_reference_id')->nullable();
                $t->index('external_reference_id', 'pts_external_reference_idx');

                return;
            }
            $t->foreignId('external_reference_id')->nullable()->after('invoice_item_id')
                ->constrained('external_references', indexName: 'pts_external_reference_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        if (! Schema::hasColumn('project_time_shortfalls', 'external_reference_id')) {
            return;
        }
        Schema::table('project_time_shortfalls', function (Blueprint $t): void {
            if ($this->isSqlite()) {
                $t->dropIndex('pts_external_reference_idx');
            } else {
                $t->dropForeign('pts_external_reference_fk');
            }
            $t->dropColumn('external_reference_id');
        });
    }

    private function isSqlite(): bool {
        return Schema::getConnection()->getDriverName() === 'sqlite';
    }
};
