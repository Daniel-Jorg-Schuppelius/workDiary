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
    protected $signature = 'easybill:sync {--org= : Nur diese Organisation (ID) abrufen}';

    protected $description = 'Holt fertiggestellte easybill-Belege (PDF/E-Rechnung) ins DMS zurück.';

    public function handle(EasybillDocumentPullService $pull): int {
        $query = PluginSetting::query()
            ->withoutGlobalScopes()
            ->where('plugin_id', EasybillPlugin::ID)
            ->where('enabled', true);
        if ($this->option('org') !== null) {
            $query->where('organization_id', (int) $this->option('org'));
        }

        $failures = 0;

        foreach ($query->pluck('organization_id') as $organizationId) {
            try {
                $counters = $pull->pull((int) $organizationId);
                $this->info(sprintf('Org %d: %s', (int) $organizationId, (string) json_encode($counters)));
            } catch (Throwable $e) {
                $failures++;
                $this->error(sprintf('Org %d: %s', (int) $organizationId, class_basename($e)));
                report($e);
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
