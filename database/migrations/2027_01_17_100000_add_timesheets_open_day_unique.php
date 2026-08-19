<?php
/*
 * Created on   : Wed Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_17_100000_add_timesheets_open_day_unique.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * DB-Backstop zu TimesheetResolver: höchstens ein OFFENER Stundenzettel je
 * Projekt, Nutzer und Tag. Ohne ihn konnten Sidebar-Anlage und Stoppuhr-Start
 * zwei Zettel für denselben Einsatz erzeugen.
 *
 * Signierte/gesperrte Zettel bleiben ausgenommen — nach der Kundenfreigabe
 * muss am selben Tag ein zweiter Einsatz erfassbar bleiben. Deshalb ein
 * partieller Index (sqlite/pgsql) bzw. eine generierte Spalte (MySQL, kennt
 * kein WHERE am Index) analog zu 2026_12_04_100200 für Anwesenheiten.
 */
return new class extends Migration {
    /** @var array<int, string> */
    private const OPEN_STATUS = ['draft', 'submitted'];

    public function up(): void {
        $this->guardAgainstExistingDuplicates();

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            Schema::getConnection()->statement(
                'CREATE UNIQUE INDEX timesheets_open_day_unique ON timesheets '
                    . "(project_id, user_id, work_date) WHERE status IN ('draft', 'submitted')"
            );

            return;
        }

        if (! $this->isMysqlFamily()) {
            return;
        }

        Schema::getConnection()->statement(
            'ALTER TABLE timesheets ADD COLUMN open_work_date DATE '
                . "GENERATED ALWAYS AS (CASE WHEN status IN ('draft', 'submitted') THEN work_date END) VIRTUAL"
        );

        Schema::table('timesheets', function (Blueprint $table): void {
            // NULL kollidiert in MySQL nicht — signierte Zettel fallen aus dem Index.
            $table->unique(['project_id', 'user_id', 'open_work_date'], 'timesheets_open_day_unique');
        });
    }

    public function down(): void {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            Schema::getConnection()->statement('DROP INDEX IF EXISTS timesheets_open_day_unique');

            return;
        }

        if (! $this->isMysqlFamily()) {
            return;
        }

        Schema::table('timesheets', function (Blueprint $table): void {
            $table->dropUnique('timesheets_open_day_unique');
            $table->dropColumn('open_work_date');
        });
    }

    /**
     * Bestandsdaten können bereits Doppel enthalten — der Index würde mitten im
     * Deploy scheitern. Lieber vorher mit einer Ansage abbrechen, die sagt,
     * womit sich die Fälle ansehen lassen. Zusammenlegen ist Handarbeit: welcher
     * Zettel der „richtige" ist, hängt an Kundendaten und Unterschrift.
     */
    private function guardAgainstExistingDuplicates(): void {
        $duplicates = DB::table('timesheets')
            ->selectRaw('project_id, user_id, work_date, COUNT(*) as total')
            ->whereIn('status', self::OPEN_STATUS)
            ->whereNotNull('project_id')
            ->groupBy('project_id', 'user_id', 'work_date')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicates === 0) {
            return;
        }

        throw new RuntimeException(
            "Es gibt {$duplicates} Tag(e) mit mehreren offenen Stundenzetteln — der Unique-Index "
                . 'kann erst danach greifen. Betroffene Fälle auflisten mit: php artisan timesheets:duplicates'
        );
    }

    private function isMysqlFamily(): bool {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};
