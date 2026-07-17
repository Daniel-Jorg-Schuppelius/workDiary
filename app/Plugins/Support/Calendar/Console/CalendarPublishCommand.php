<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendarPublishCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Calendar\Console;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\Organization;
use App\Plugins\Contracts\CalendarPublisher;
use Illuminate\Console\Command;

/**
 * Gemeinsamer Publish-Lauf der Kalender-Plugins (Konsolidierung B5 — gehoben
 * aus den byte-gleichen CalDAV-/Msgraph-/GoogleCalendar-Commands, Muster
 * {@see \App\Plugins\Support\Mirror\Console\MirrorBackfillCommand}):
 * publiziert Termine je Organisation idempotent über stabile UIDs —
 * wiederholte Läufe erzeugen keine Dubletten. Signatur/Beschreibung bleiben
 * bei den Plugin-Ableitungen.
 */
abstract class CalendarPublishCommand extends Command {
    use IteratesOrganizations;

    /** Das Kalender-Plugin dieses Commands (CalDAV, Microsoft 365, Google). */
    abstract protected function plugin(): CalendarPublisher;

    public function handle(): int {
        $plugin = $this->plugin();

        $this->forEachOrganization(function (Organization $org) use ($plugin): void {
            $r = $plugin->publishCalendar($org);
            $this->info(sprintf(
                'Organisation #%d (%s): published %d, deleted %d, unchanged %d, failed %d',
                $org->id, $org->name, $r['published'], $r['deleted'], $r['unchanged'], $r['failed'],
            ));
        });

        return self::SUCCESS;
    }
}
