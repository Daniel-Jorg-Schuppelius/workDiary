<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleCalendarPublishCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\GoogleCalendar\Console;

use App\Plugins\Contracts\CalendarPublisher;
use App\Plugins\GoogleCalendar\GoogleCalendarPlugin;
use App\Plugins\Support\Calendar\Console\CalendarPublishCommand;

/**
 * Publiziert Termine je Organisation in den verbundenen Google-Kalender
 * (MVP-328, Bauturbo A8). Manuell aus der Admin-UI aufrufbar
 * (CalDAV-Muster: `caldav:publish`).
 */
class GoogleCalendarPublishCommand extends CalendarPublishCommand {
    protected $signature = 'google-calendar:publish
        {--organization= : ID einer einzelnen Organisation, sonst alle}';

    protected $description = 'Publiziert WorkDiary-Termine idempotent in den verbundenen Google-Kalender.';

    protected function plugin(): CalendarPublisher {
        return new GoogleCalendarPlugin();
    }
}
