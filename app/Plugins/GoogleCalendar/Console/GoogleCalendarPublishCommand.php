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

use App\Models\Organization;
use App\Plugins\GoogleCalendar\GoogleCalendarPlugin;
use Illuminate\Console\Command;
use Throwable;

/**
 * Publiziert Termine je Organisation in den verbundenen Google-Kalender
 * (MVP-328, Bauturbo A8). Manuell aus der Admin-UI aufrufbar
 * (CalDAV-Muster: `caldav:publish`). Idempotent über stabile UIDs —
 * wiederholte Läufe erzeugen keine Dubletten.
 */
class GoogleCalendarPublishCommand extends Command {
    protected $signature = 'google-calendar:publish
        {--organization= : ID einer einzelnen Organisation, sonst alle}';

    protected $description = 'Publiziert WorkDiary-Termine idempotent in den verbundenen Google-Kalender.';

    public function handle(): int {
        $orgId = $this->option('organization');
        $query = Organization::query();
        if ($orgId !== null && $orgId !== '') {
            $query->whereKey((int) $orgId);
        }

        $plugin = new GoogleCalendarPlugin();

        foreach ($query->get() as $org) {
            // Org-Kontext für nachgelagerte (scoped) Operationen binden.
            app()->instance('currentOrganization', $org);

            try {
                $r = $plugin->publishCalendar($org);
                $this->info(sprintf(
                    'Organisation #%d (%s): published %d, deleted %d, unchanged %d, failed %d',
                    $org->id, $org->name, $r['published'], $r['deleted'], $r['unchanged'], $r['failed'],
                ));
            } catch (Throwable $e) {
                $this->error(sprintf('Organisation #%d (%s): Abbruch — %s', $org->id, $org->name, $e->getMessage()));
            }
        }

        return self::SUCCESS;
    }
}
