<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DemoResetCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Demo;

use App\Enums\Demo\DemoIndustry;
use App\Models\{AuditLog, Organization};
use App\Services\Demo\DemoSeederService;
use Illuminate\Console\Command;

/**
 * Setzt eine Demo-Organisation auf einen sauberen Stand zurück (Feature 040).
 *
 * HARTER SCHUTZ: Wirkt ausschließlich auf Organisationen mit is_demo = true.
 * Echte Mandanten werden niemals angefasst — der Service verweigert den Reset
 * für nicht-Demo-Orgs zusätzlich (Defense in Depth).
 */
class DemoResetCommand extends Command {
    protected $signature = 'demo:reset {org? : Organisations-ID (Default: erste Demo-Org)} {--industry= : Branche neu wählen (sonst beibehalten)} {--all : Alle Demo-Orgs zurücksetzen}';

    protected $description = 'Setzt Demo-Organisationen zurück (löscht Demo-Daten und seedet neu). Nur is_demo-Orgs.';

    public function handle(DemoSeederService $seeder): int {
        $industry = $this->option('industry') !== null
            ? DemoIndustry::fromKey((string) $this->option('industry'))
            : null;

        $organizations = $this->resolveOrganizations();
        if ($organizations->isEmpty()) {
            $this->error('Keine Demo-Organisation gefunden (nur is_demo=true wird zurückgesetzt).');

            return self::FAILURE;
        }

        foreach ($organizations as $org) {
            // Doppelter Schutz: nur Demo-Orgs. Echte Mandanten überspringen.
            if (! $org->is_demo) {
                $this->warn(sprintf('Übersprungen (kein Demo-Mandant): %s (#%d).', $org->name, $org->id));

                continue;
            }

            $counts = $seeder->reset($org, null, $industry);

            AuditLog::query()->create([
                'organization_id' => $org->id,
                'user_id' => null,
                'event' => 'demo.reset',
                'auditable_type' => Organization::class,
                'auditable_id' => $org->id,
                'changes' => $counts,
            ]);

            $this->info(sprintf(
                'Demo-Mandant „%s" (Branche %s) zurückgesetzt.',
                $org->name,
                (string) $counts['industry'],
            ));
        }

        return self::SUCCESS;
    }

    /** @return \Illuminate\Support\Collection<int, Organization> */
    private function resolveOrganizations(): \Illuminate\Support\Collection {
        if ((bool) $this->option('all')) {
            return Organization::query()->where('is_demo', true)->orderBy('id')->get();
        }

        $arg = $this->argument('org');
        if ($arg !== null) {
            $org = Organization::query()->find((int) $arg);

            return $org !== null ? collect([$org]) : collect();
        }

        $org = Organization::query()->where('is_demo', true)->orderBy('id')->first();

        return $org !== null ? collect([$org]) : collect();
    }
}
