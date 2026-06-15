<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DemoSeedCommand.php
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
 * Befüllt eine Organisation mit branchenspezifischen Demo-Daten (Feature 040).
 *
 * Markiert die Org als Demo-Mandant (is_demo) und installiert das passende
 * Branchenprofil samt End-to-End-Beispielauftrag.
 */
class DemoSeedCommand extends Command {
    protected $signature = 'demo:seed {org? : Organisations-ID (Default: erste Org)} {--industry= : Musterbranche (it-service|elektro|facility)} {--list : Verfügbare Musterbranchen anzeigen}';

    protected $description = 'Erzeugt branchenspezifische Demo-Daten für eine Organisation (Feature 040).';

    public function handle(DemoSeederService $seeder): int {
        if ((bool) $this->option('list')) {
            $this->line('Verfügbare Musterbranchen:');
            foreach (DemoIndustry::all() as $industry) {
                $this->line(sprintf('  %-12s %s (Profil: %s)', $industry->value, $industry->label(), $industry->branchProfileCode()));
            }

            return self::SUCCESS;
        }

        $org = $this->resolveOrganization();
        if ($org === null) {
            $this->error('Organisation nicht gefunden.');

            return self::FAILURE;
        }

        $industry = DemoIndustry::fromKey($this->option('industry') !== null ? (string) $this->option('industry') : null);

        $counts = $seeder->seed($org, null, $industry);

        AuditLog::query()->create([
            'organization_id' => $org->id,
            'user_id' => null,
            'event' => 'demo.seeded',
            'auditable_type' => Organization::class,
            'auditable_id' => $org->id,
            'changes' => $counts,
        ]);

        $this->info(sprintf(
            'Demo-Mandant „%s" (Branche %s) befüllt: %d Kunden, %d Projekte, %d Aufträge, %d Material, %d Protokoll(e).',
            $org->name,
            $industry->label(),
            (int) $counts['customers'],
            (int) $counts['projects'],
            (int) $counts['main_diary_entries'] + (int) $counts['background_diary_entries'],
            (int) $counts['materials'],
            (int) $counts['protocols'],
        ));

        return self::SUCCESS;
    }

    private function resolveOrganization(): ?Organization {
        $arg = $this->argument('org');
        if ($arg !== null) {
            return Organization::query()->find((int) $arg);
        }

        return Organization::query()->orderBy('id')->first();
    }
}
