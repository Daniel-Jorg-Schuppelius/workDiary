<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_280000_add_run_options_to_scheduled_job_overrides.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1619 (N-09): Zeitzone, Ruhezeit und Wiederholung je Scheduler-Job
 * (Feature 067). Leer = Betriebsfenster und Zeitzone der Installation.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('scheduled_job_overrides', function (Blueprint $table): void {
            if (! Schema::hasColumn('scheduled_job_overrides', 'timezone')) {
                $table->string('timezone', 64)->nullable()->after('cadence');
            }
            if (! Schema::hasColumn('scheduled_job_overrides', 'quiet_from')) {
                $table->string('quiet_from', 5)->nullable()->after('timezone');
                $table->string('quiet_until', 5)->nullable()->after('quiet_from');
            }
            if (! Schema::hasColumn('scheduled_job_overrides', 'retry_attempts')) {
                $table->unsignedTinyInteger('retry_attempts')->default(0)->after('quiet_until');
                $table->unsignedSmallInteger('retry_delay_minutes')->nullable()->after('retry_attempts');
            }
        });
    }

    public function down(): void {
        Schema::table('scheduled_job_overrides', function (Blueprint $table): void {
            $table->dropColumn(['timezone', 'quiet_from', 'quiet_until', 'retry_attempts', 'retry_delay_minutes']);
        });
    }
};
