<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingContactImportCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\ContactImport;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\Platform\Organization;
use App\Plugins\Support\Console\ChecksPluginSwitch;
use Illuminate\Console\Command;
use Throwable;

/**
 * Geplanter Kontakt-Rückweg (MVP-1502): je Organisation mit eingeschaltetem
 * Plugin ein Lauf von {@see AccountingContactImporter::run()} im Kontext der
 * Organisation, deren Einstellung „Buchhaltung führt“ der Lauf liest.
 */
abstract class AccountingContactImportCommand extends Command {
    use ChecksPluginSwitch;
    use IteratesOrganizations;

    abstract protected function importer(): AccountingContactImporter;

    public function handle(): int {
        $importer = $this->importer();
        $failures = $this->forEachOrganization(
            function (Organization $organization) use ($importer): void {
                if (! $this->pluginEnabledFor($importer->pluginId(), (int) $organization->id)) {
                    return;
                }
                $counters = $importer->run($organization);
                $this->info(sprintf(
                    'Organisation #%d: %d gelesen, %d verknüpft, %d Klärfälle, %d übersprungen',
                    $organization->id, $counters['read'], $counters['linked'], $counters['inboxed'], $counters['skipped'],
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
