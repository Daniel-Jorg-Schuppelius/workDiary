<?php
/*
 * Created on   : Wed Jul 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_01_140100_create_time_export_lines_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggregierte Export-Zeilen je User × Lohnart × Kostenstelle (MVP-019).
 *
 * - wage_type: work.normal | work.night | work.sunday | work.holiday |
 *              work.oncall | absence.vacation | absence.sick | travel.time
 * - cost_center: optional, je nach Branchen-/Mandantenprofil
 * - quantity: i. d. R. Stunden (DECIMAL 10,4), Einheit in `unit`
 * - source_refs: JSON mit den IDs der aggregierten Quell-Datensätze
 *                (Attendance, Vacation, Sickness, OnCallShift, …)
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('time_export_lines', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('time_export_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('wage_type', 32);
            $t->string('cost_center', 32)->nullable();
            $t->decimal('quantity', 10, 4)->default(0);
            $t->string('unit', 16)->default('h');
            $t->date('period_start');
            $t->date('period_end');
            $t->text('note')->nullable();
            $t->json('source_refs')->nullable();
            $t->timestamps();

            $t->index(['time_export_id', 'user_id'], 'tel_export_user_idx');
            $t->index(['time_export_id', 'wage_type'], 'tel_export_wage_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('time_export_lines');
    }
};
