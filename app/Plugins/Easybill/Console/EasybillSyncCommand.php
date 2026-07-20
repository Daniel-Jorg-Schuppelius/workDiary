<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EasybillSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Easybill\Console;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\PluginSetting;
use App\Plugins\Easybill\EasybillPlugin;
use App\Plugins\Easybill\Services\EasybillDocumentPullService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Periodischer easybill-Rückabruf (MVP-431, W1.3): holt fertiggestellte
 * Belege (PDF/E-Rechnung) für alle Organisationen mit aktiviertem Plugin ins
 * DMS. Scheduler-Eintrag `easybill.sync` (config/scheduler.php); Fehler
 * einer Organisation stoppen die anderen nicht.
 */
class EasybillSyncCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'easybill:sync {--org= : Nur diese Organisation (ID) abrufen}';

    protected $description = 'Holt fertiggestellte easybill-Belege (PDF/E-Rechnung) ins DMS zurück.';

    public function handle(EasybillDocumentPullService $pull): int {
        // C6-Skelett (Vollaudit 2026-07, M55): IteratesOrganizations bindet je
        // Org den currentOrganization-Kontext (inkl. Restore).
        $failures = $this->forEachOrganization(
            function (\App\Models\Organization $organization) use ($pull): void {
                $counters = $pull->pull((int) $organization->id);
                $this->info(sprintf('Org %d: %s', $organization->id, (string) json_encode($counters)));
            },
            onError: function (\App\Models\Organization $organization, Throwable $e): void {
                $this->error(sprintf('Org %d: %s', $organization->id, class_basename($e)));
                report($e);
            },
            option: 'org',
            scope: fn($query) => $query->whereIn('id', PluginSetting::query()
                ->withoutGlobalScopes()
                ->where('plugin_id', EasybillPlugin::ID)
                ->where('enabled', true)
                ->select('organization_id')),
        );

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
