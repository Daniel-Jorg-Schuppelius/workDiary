<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalDavImportCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\CalDav\Console;

use App\Plugins\CalDav\CalDavPlugin;
use App\Plugins\CalDav\Models\CalDavConnection;
use App\Plugins\CalDav\Services\CalDavCalendarImportService;
use App\Plugins\Support\Calendar\Console\CalendarImportCommand;
use App\Plugins\Support\Calendar\RemoteCalendarConnection;

/**
 * Kalender-Rückimport CalDAV (Feature 121, MVP-610b): Delta aller Anbindungen
 * mit `two_way`-Opt-in → Integrations-Inbox-Fälle. Fehler zählen auf die
 * Verbindungs-Gesundheit.
 *
 * @extends CalendarImportCommand<CalDavConnection>
 */
class CalDavImportCommand extends CalendarImportCommand {
    protected $signature = 'caldav:import
        {--organization= : ID einer einzelnen Organisation, sonst alle}';

    protected $description = 'Importiert Änderungen aus den CalDAV-Kalendern als Integrations-Inbox-Vorschläge (Zwei-Wege, Opt-in).';

    protected function pluginId(): string {
        return CalDavPlugin::ID;
    }

    protected function connectionModel(): string {
        return CalDavConnection::class;
    }

    protected function import(RemoteCalendarConnection $connection): array {
        return app(CalDavCalendarImportService::class)->run($connection);
    }

    protected function label(): string {
        return 'CalDAV-Rückimport';
    }
}
