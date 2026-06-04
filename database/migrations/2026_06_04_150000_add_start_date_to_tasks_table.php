<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_04_150000_add_start_date_to_tasks_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planungs-Startdatum für Aufgaben: zusammen mit dem bestehenden `due_date`
 * (Deadline) ergibt sich der Bearbeitungszeitraum für den Projekt-Zeitstrahl.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->date('start_date')->nullable()->after('due_date');
        });
    }

    public function down(): void {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn('start_date');
        });
    }
};
