<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EtsySyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Etsy\Console;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\{Organization, PluginSetting};
use App\Plugins\Etsy\EtsyPlugin;
use App\Plugins\Etsy\Services\{EtsyLedgerImportService, EtsyReceiptImportService};
use Illuminate\Console\Command;
use Throwable;

/**
 * Periodischer Etsy-Sync (Feature 101, MVP-495/498): Bestellimport
 * Inbox-First + Ledger-Fenster je Organisation mit aktiviertem Plugin.
 * Scheduler-Eintrag `etsy.sync` (config/scheduler.php); Fehler einer
 * Organisation stoppen die anderen nicht.
 */
class EtsySyncCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'etsy:sync {--org= : Nur diese Organisation (ID) synchronisieren}';

    protected $description = 'Importiert Etsy-Bestellungen (Inbox-First) und zieht das Gebühren-/Auszahlungs-Ledger nach.';

    public function handle(EtsyReceiptImportService $receipts, EtsyLedgerImportService $ledger): int {
        $failures = $this->forEachOrganization(
            function (Organization $organization) use ($receipts, $ledger): void {
                $counters = $receipts->import($organization) + ['ledger' => $ledger->import($organization)];
                $this->info(sprintf('Org %d: %s', $organization->id, (string) json_encode($counters)));
            },
            onError: function (Organization $organization, Throwable $e): void {
                $this->error(sprintf('Org %d: %s', $organization->id, class_basename($e)));
                report($e);
            },
            option: 'org',
            scope: fn($query) => $query->whereIn('id', PluginSetting::query()
                ->withoutGlobalScopes()
                ->where('plugin_id', EtsyPlugin::ID)
                ->where('enabled', true)
                ->select('organization_id')),
        );

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
