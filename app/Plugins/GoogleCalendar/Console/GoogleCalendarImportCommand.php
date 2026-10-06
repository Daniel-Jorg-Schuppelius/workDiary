<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleCalendarImportCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\GoogleCalendar\Console;

use App\Plugins\GoogleCalendar\GoogleCalendarPlugin;
use App\Plugins\GoogleCalendar\Models\GoogleCalendarConnection;
use App\Plugins\GoogleCalendar\Services\GoogleCalendarImportService;
use App\Plugins\Support\Calendar\Console\CalendarImportCommand;
use App\Plugins\Support\Calendar\RemoteCalendarConnection;

/**
 * Kalender-Rückimport Google (Feature 121, MVP-610a): Änderungsliste aller
 * Verbindungen mit `two_way`-Opt-in → Integrations-Inbox-Fälle. Fehler zählen
 * auf die Verbindungs-Gesundheit (Auto-Disable ab Schwellwert).
 *
 * @extends CalendarImportCommand<GoogleCalendarConnection>
 */
class GoogleCalendarImportCommand extends CalendarImportCommand {
    protected $signature = 'google-calendar:import
        {--organization= : ID einer einzelnen Organisation, sonst alle}';

    protected $description = 'Importiert Änderungen aus dem Google-Kalender als Integrations-Inbox-Vorschläge (Zwei-Wege, Opt-in).';

    protected function pluginId(): string {
        return GoogleCalendarPlugin::ID;
    }

    protected function connectionModel(): string {
        return GoogleCalendarConnection::class;
    }

    protected function import(RemoteCalendarConnection $connection): array {
        return app(GoogleCalendarImportService::class)->run($connection);
    }

    protected function label(): string {
        return 'Google-Kalender-Rückimport';
    }
}
