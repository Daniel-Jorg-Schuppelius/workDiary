<?php
/*
 * Created on   : Sun Aug 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_04_100200_add_attendances_open_unique_for_mysql.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Pendant zum partiellen Index aus 2026_05_17_120001 (nur sqlite/pgsql):
     * MySQL/MariaDB kennen keine WHERE-Indizes, d. h. "nur eine offene
     * Anwesenheit pro User" war dort bisher NICHT auf DB-Ebene erzwungen.
     * Ersatz: generierte Spalte (user_id nur solange ended_at NULL) mit
     * Unique-Index — NULL-Werte kollidieren nicht.
     */
    public function up(): void {
        if (!$this->isMysqlFamily()) {
            return;
        }

        Schema::getConnection()->statement(
            'ALTER TABLE attendances ADD COLUMN open_user_id BIGINT UNSIGNED '
                . 'GENERATED ALWAYS AS (CASE WHEN ended_at IS NULL THEN user_id END) VIRTUAL'
        );

        Schema::table('attendances', function (Blueprint $table): void {
            $table->unique('open_user_id', 'attendances_user_open_unique');
        });
    }

    public function down(): void {
        if (!$this->isMysqlFamily()) {
            return;
        }

        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropUnique('attendances_user_open_unique');
            $table->dropColumn('open_user_id');
        });
    }

    private function isMysqlFamily(): bool {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};
