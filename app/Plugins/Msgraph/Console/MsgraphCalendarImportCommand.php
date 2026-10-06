<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphCalendarImportCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Msgraph\Console;

use App\Plugins\Msgraph\Models\MsgraphConnection;
use App\Plugins\Msgraph\MsgraphPlugin;
use App\Plugins\Msgraph\Services\MsgraphCalendarImportService;
use App\Plugins\Support\Calendar\Console\CalendarImportCommand;
use App\Plugins\Support\Calendar\RemoteCalendarConnection;

/**
 * Zwei-Wege-Kalender-Rückimport (Feature 102, C3): calendarView-Delta aller
 * Verbindungen mit `two_way`-Opt-in → Integrations-Inbox-Fälle (Vorschläge,
 * Konflikte, Lösch-Hinweise). Fehler zählen auf den Verbindungs-Health.
 *
 * @extends CalendarImportCommand<MsgraphConnection>
 */
class MsgraphCalendarImportCommand extends CalendarImportCommand {
    protected $signature = 'msgraph:calendar-import
        {--organization= : ID einer einzelnen Organisation, sonst alle}';

    protected $description = 'Importiert Änderungen aus dem Microsoft-365-Kalender als Integrations-Inbox-Vorschläge (Zwei-Wege, Opt-in).';

    protected function pluginId(): string {
        return MsgraphPlugin::ID;
    }

    protected function connectionModel(): string {
        return MsgraphConnection::class;
    }

    protected function import(RemoteCalendarConnection $connection): array {
        return app(MsgraphCalendarImportService::class)->run($connection);
    }

    protected function label(): string {
        return 'Kalender-Rückimport';
    }
}
