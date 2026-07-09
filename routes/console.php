<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : console.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Scheduling\SchedulerRegistrar;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Feature 067 (MVP-175/180): Alle wiederkehrenden Jobs kommen aus der
 * Scheduler-Job-Registry (config/scheduler.php) — hier werden KEINE
 * Schedule::command()-Einträge mehr hartcodiert. Neue Jobs in der
 * Registry deklarieren; Umplanung/Pausen laufen über die Adminseite
 * (MVP-176) bzw. scheduled_job_overrides.
 */
app(SchedulerRegistrar::class)->register(app(Schedule::class));
