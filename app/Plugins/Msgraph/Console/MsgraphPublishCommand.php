<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphPublishCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Msgraph\Console;

use App\Plugins\Contracts\CalendarPublisher;
use App\Plugins\Msgraph\MsgraphPlugin;
use App\Plugins\Support\Calendar\Console\CalendarPublishCommand;

/**
 * Publiziert Termine je Organisation in den verbundenen Microsoft-365-Kalender
 * (MVP-328, Bauturbo A8). Manuell aus der Admin-UI aufrufbar
 * (CalDAV-Muster: `caldav:publish`).
 */
class MsgraphPublishCommand extends CalendarPublishCommand {
    protected $signature = 'msgraph:publish
        {--organization= : ID einer einzelnen Organisation, sonst alle}';

    protected $description = 'Publiziert WorkDiary-Termine idempotent in den verbundenen Microsoft-365-Kalender.';

    protected function plugin(): CalendarPublisher {
        return new MsgraphPlugin();
    }
}
