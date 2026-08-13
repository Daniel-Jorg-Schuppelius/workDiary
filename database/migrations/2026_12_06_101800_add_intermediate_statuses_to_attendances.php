<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_101800_add_intermediate_statuses_to_attendances.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-532 (Feature 103, Q1-Drittabgleich): Zwischen-Status Homeoffice und
 * Dienstgang als Toggle-Paare analog Pause — sie KLASSIFIZIEREN die
 * laufende Anwesenheit (arbeitet, aber nicht im Haus), zählen aber nicht
 * als Pause und mindern die Arbeitszeit nicht.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->timestamp('homeoffice_started_at')->nullable()->after('break_started_at');
            $table->unsignedInteger('homeoffice_minutes')->default(0)->after('homeoffice_started_at');
            $table->timestamp('errand_started_at')->nullable()->after('homeoffice_minutes');
            $table->unsignedInteger('errand_minutes')->default(0)->after('errand_started_at');
        });
    }

    public function down(): void {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropColumn(['homeoffice_started_at', 'homeoffice_minutes', 'errand_started_at', 'errand_minutes']);
        });
    }
};
