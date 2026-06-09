<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurgeDowngradedModules.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Plans;

use App\Models\PlanModuleGrace;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Entfernt nach Ablauf der Downgrade-Karenz die Daten der verlorenen Module –
 * ABER nur fuer Module mit `purgeable_on_downgrade=true`. Gesetzlich
 * aufbewahrungspflichtige Module (Rechnungen/Belege/Arbeitszeit/Hinweisgeber)
 * werden NIE geloescht, nur als verarbeitet markiert (Zugriff sperrt schon das
 * Gate). Loeschung ist org-scoped; DB-FK-Cascade entfernt Kind-Datensaetze.
 */
class PurgeDowngradedModules extends Command {
    protected $signature = 'plans:purge {--dry-run : Nur anzeigen, nichts loeschen} {--org= : Auf eine Organisation beschraenken}';

    protected $description = 'Loescht nach Karenzablauf die Daten purgebarer Module aus Downgrades (Aufbewahrungspflichtige bleiben).';

    public function handle(): int {
        $dryRun = (bool) $this->option('dry-run');
        $orgId = $this->option('org') !== null ? (int) $this->option('org') : null;

        $query = PlanModuleGrace::query()
            ->whereNull('purged_at')
            ->where('grace_until', '<=', Carbon::now());
        if ($orgId !== null) {
            $query->where('organization_id', $orgId);
        }

        /** @var \Illuminate\Support\Collection<int, PlanModuleGrace> $due */
        $due = $query->get();

        if ($due->isEmpty()) {
            $this->info('Keine faelligen Downgrade-Karenzen.');

            return self::SUCCESS;
        }

        $purgeable = (array) config('plans.purgeable_on_downgrade', []);
        $modelMap = (array) config('plans.purge_models', []);
        $totalDeleted = 0;

        foreach ($due as $grace) {
            $module = $grace->module;
            $label = (string) (config('plans.labels')[$module] ?? $module);

            if (($purgeable[$module] ?? false) !== true) {
                // Aufbewahrungspflichtig: niemals loeschen, nur als verarbeitet markieren.
                $this->line("  <fg=yellow>BEHALTEN</> Org #{$grace->organization_id} {$label}: aufbewahrungspflichtig – keine Loeschung.");
                if (! $dryRun) {
                    $grace->forceFill(['purged_at' => Carbon::now()])->save();
                }

                continue;
            }

            /** @var list<class-string<Model>> $models */
            $models = (array) ($modelMap[$module] ?? []);
            $deleted = 0;
            foreach ($models as $modelClass) {
                $scoped = $modelClass::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $grace->organization_id);
                $deleted += $dryRun ? $scoped->count() : (int) $scoped->delete();
            }
            $totalDeleted += $deleted;

            $verb = $dryRun ? 'WUERDE LOESCHEN' : 'GELOESCHT';
            $this->line("  <fg=red>{$verb}</> Org #{$grace->organization_id} {$label}: {$deleted} Datensaetze.");

            if (! $dryRun) {
                $grace->forceFill(['purged_at' => Carbon::now()])->save();
            }
        }

        $this->newLine();
        $this->info(($dryRun ? '[Dry-Run] ' : '') . "{$due->count()} Karenz(en) verarbeitet, {$totalDeleted} Datensaetze "
            . ($dryRun ? 'betroffen.' : 'geloescht.'));

        return self::SUCCESS;
    }
}
