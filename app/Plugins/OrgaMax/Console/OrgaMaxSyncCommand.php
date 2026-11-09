<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax\Console;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\Platform\Organization;
use App\Plugins\OrgaMax\Enums\OrgaMaxConnectionStatus;
use App\Plugins\OrgaMax\Models\OrgaMaxConnection;
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use App\Plugins\OrgaMax\Services\{OrgaMaxArticleCatalogSource, OrgaMaxSyncService, OrgaMaxVoucherPullService};
use App\Plugins\Support\Console\ChecksPluginSwitch;
use App\Services\Finance\Accounting\Articles\ArticleMirror;
use App\Services\Finance\Accounting\Vouchers\VoucherMirror;
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Console\Command;
use Throwable;

/**
 * Budgetierter orgaMAX-Abgleich (Feature 077, MVP-313): je aktiver
 * Verbindung ein Lauf mit Checkpoints und Laufbudget; Fehler einer
 * Organisation stoppen die anderen nicht. Der geplante Lauf des Belegspiegels
 * (MVP-1501): nach den Stammdaten der Artikelkatalog, dann Rechnungen und
 * Ausgaben samt Positionen der neuen Rechnungen.
 */
class OrgaMaxSyncCommand extends Command {
    use ChecksPluginSwitch;
    use IteratesOrganizations;

    /** Rechnungen je Lauf, deren Positionen nachgeladen werden (Einzelabrufe `GET /invoice/{id}`). */
    private const int LINE_BATCH = 100;

    protected $signature = 'orgamax:sync {--org= : Nur diese Organisations-ID abgleichen}';

    protected $description = 'Gleicht orgaMAX-Buchhaltung ab (Projektionen, Checkpoints, Laufbudget).';

    public function handle(OrgaMaxSyncService $sync, OrgaMaxVoucherPullService $vouchers, OrgaMaxArticleCatalogSource $catalog, ArticleMirror $articles, VoucherMirror $mirror): int {
        $query = OrgaMaxConnection::query()
            ->withoutGlobalScopes()
            ->where('status', OrgaMaxConnectionStatus::Active);
        if ($this->option('org') !== null) {
            $query->where('organization_id', (int) $this->option('org'));
        }

        $failures = 0;
        foreach ($query->orderBy('organization_id')->get() as $connection) {
            if (! $this->pluginEnabledFor(OrgaMaxPlugin::ID, (int) $connection->organization_id)) {
                continue;
            }

            $organization = Organization::query()->withoutGlobalScopes()->find($connection->organization_id);
            if ($organization === null) {
                continue;
            }

            $this->withOrganizationContext($organization, function () use ($sync, $vouchers, $catalog, $articles, $mirror, $connection, &$failures): void {
                $organizationId = (int) $connection->organization_id;
                try {
                    $counters = $sync->run($connection);
                    // Katalog vor den Belegen (gap-09): neue Positionen tragen so gleich den Katalogschlüssel.
                    if ($catalog->isConfigured($organizationId)) {
                        try {
                            $counters['catalog_read'] = $articles->sync($organizationId, $catalog)['read'];
                        } catch (Throwable $e) {
                            $failures++;
                            report($e);
                            $this->error(sprintf('Org %d: Artikel nicht vollständig gelesen (%s).', $organizationId, class_basename($e)));
                        }
                    }
                    $pulled = $vouchers->pull($organizationId, max(1, (int) config('plugins.orgamax.sync_page_budget', 10)));
                    $counters += ['vouchers_read' => $pulled['read'], 'vouchers_created' => $pulled['created'], 'vouchers_updated' => $pulled['updated']];
                    if ($vouchers->isConfigured($organizationId)) {
                        $counters['voucher_lines'] = $mirror->pullPendingLines($vouchers, $organizationId, self::LINE_BATCH);
                    }
                    $this->info(sprintf('Org %d: %s', $organizationId, JsonHelper::encode($counters)));
                } catch (Throwable $e) {
                    $failures++;
                    $connection->forceFill(['last_error' => mb_substr($e::class, 0, 200)])->save();
                    $this->error(sprintf('Org %d: Abgleich fehlgeschlagen (%s).', $connection->organization_id, $e::class));
                }
            });
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
