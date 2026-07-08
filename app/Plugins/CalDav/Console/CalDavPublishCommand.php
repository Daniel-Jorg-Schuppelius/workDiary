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

use App\Models\Organization;
use App\Plugins\CalDav\CalDavPlugin;
use Illuminate\Console\Command;
use Throwable;

/**
 * Publiziert Termine je Organisation in die konfigurierten CalDAV-Kalender
 * (Feature 058, MVP-126). Läuft im Scheduler und manuell aus der Admin-UI.
 * Idempotent über stabile UIDs — wiederholte Läufe erzeugen keine Dubletten.
 */
class CalDavPublishCommand extends Command {
    protected $signature = 'caldav:publish
        {--organization= : ID einer einzelnen Organisation, sonst alle}';

    protected $description = 'Publiziert WorkDiary-Termine idempotent in die konfigurierten CalDAV-Kalender.';

    public function handle(): int {
        $orgId = $this->option('organization');
        $query = Organization::query();
        if ($orgId !== null && $orgId !== '') {
            $query->whereKey((int) $orgId);
        }

        $plugin = new CalDavPlugin();

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
