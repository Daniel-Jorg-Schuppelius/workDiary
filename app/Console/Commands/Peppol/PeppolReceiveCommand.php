<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeppolReceiveCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Peppol;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\{Organization, PluginSetting};
use App\Plugins\PeppolAccessPoint\PeppolAccessPointPlugin;
use App\Services\Peppol\PeppolInboundService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Holt eingegangene Peppol-Belege beim Access-Point-Provider ab (Feature 066,
 * MVP-734) und übergibt sie an die kanalneutrale Rechnungseingangs-Strecke.
 *
 * Läuft je Organisation mit aktiviertem Plugin; ein Fehler bei einer
 * Organisation stoppt die übrigen nicht. Quittiert wird erst nach der
 * Übernahme — ein nicht lesbares Dokument bleibt beim Provider liegen.
 */
class PeppolReceiveCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'peppol:receive ' . self::ORGANIZATION_OPTION . ' {--limit=50 : Höchstzahl der Dokumente je Organisation}';

    protected $description = 'Holt Peppol-Eingänge beim Access-Point-Provider ab und legt sie im Rechnungseingang an.';

    public function handle(PeppolInboundService $inbound): int {
        $limit = max(1, (int) $this->option('limit'));

        $failures = $this->forEachOrganization(
            function (Organization $organization) use ($inbound, $limit): void {
                $counters = $inbound->poll($organization, $limit);
                $this->info(sprintf('Organisation #%d: %s', $organization->id, (string) json_encode($counters)));
            },
            onError: function (Organization $organization, Throwable $e): void {
                $this->error(sprintf('Organisation #%d: %s — %s', $organization->id, class_basename($e), $e->getMessage()));
                report($e);
            },
            scope: fn ($query) => $query->whereIn('id', PluginSetting::query()
                ->withoutGlobalScopes()
                ->where('plugin_id', PeppolAccessPointPlugin::ID)
                ->where('enabled', true)
                ->select('organization_id')),
        );

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
