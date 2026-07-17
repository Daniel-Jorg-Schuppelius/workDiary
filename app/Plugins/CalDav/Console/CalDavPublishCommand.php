<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalDavPublishCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\CalDav\Console;

use App\Plugins\CalDav\CalDavPlugin;
use App\Plugins\Contracts\CalendarPublisher;
use App\Plugins\Support\Calendar\Console\CalendarPublishCommand;

/**
 * Publiziert Termine je Organisation in die konfigurierten CalDAV-Kalender
 * (Feature 058, MVP-126). Läuft im Scheduler und manuell aus der Admin-UI.
 */
class CalDavPublishCommand extends CalendarPublishCommand {
    protected $signature = 'caldav:publish
        {--organization= : ID einer einzelnen Organisation, sonst alle}';

    protected $description = 'Publiziert WorkDiary-Termine idempotent in die konfigurierten CalDAV-Kalender.';

    protected function plugin(): CalendarPublisher {
        return new CalDavPlugin();
    }
}
