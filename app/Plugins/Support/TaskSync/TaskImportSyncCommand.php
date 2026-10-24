<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaskImportSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\TaskSync;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\Platform\Organization;
use App\Plugins\Support\Console\ChecksPluginSwitch;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Geplanter Abgleich der Aufgabenimporte (Jira, Asana): je Organisation über
 * {@see AbstractTaskImportService::run()} — dieselbe Strecke wie der manuelle
 * Lauf, Plugin, Modul, Verbindung und Zuordnungen werden dort am
 * Ausführungszeitpunkt geprüft. `--full` liest alle Aufgaben (täglich).
 *
 * @template TLink of Model&TaskImportLink
 * @template TConnection of Model&TaskImportConnection
 */
abstract class TaskImportSyncCommand extends Command {
    use ChecksPluginSwitch;
    use IteratesOrganizations;

    abstract protected function pluginId(): string;

    /** @return AbstractTaskImportService<TLink, TConnection> */
    abstract protected function importService(): AbstractTaskImportService;

    public function handle(): int {
        $service = $this->importService();
        $full = (bool) $this->option('full');

        $failures = $this->forEachOrganization(
            function (Organization $organization) use ($service, $full): void {
                if (! $this->pluginEnabledFor($this->pluginId(), (int) $organization->id) || ! $service->mayRun($organization)) {
                    return;
                }
                try {
                    $counters = $service->run($organization, $full);
                } catch (TaskImportBusyException) {
                    $this->warn(sprintf('Organisation #%d: Lauf läuft bereits — übersprungen.', $organization->id));

                    return;
                }
                $this->info(sprintf(
                    'Organisation #%d: created %d, updated %d, unchanged %d, conflicts %d, inbox %d, failed %d',
                    $organization->id, $counters['created'], $counters['updated'], $counters['unchanged'],
                    $counters['conflicts'], $counters['inbox'], $counters['failed'],
                ));
            },
            onError: function (Organization $organization, Throwable $e): void {
                $this->error(sprintf('Organisation #%d: %s', $organization->id, class_basename($e)));
                report($e);
            },
        );

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
