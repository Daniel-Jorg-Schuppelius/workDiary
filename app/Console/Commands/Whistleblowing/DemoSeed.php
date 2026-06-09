<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DemoSeed.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Whistleblowing;

use App\Enums\Whistleblowing\{CaseCategory, ReporterMode};
use App\Models\Organization;
use App\Models\Whistleblowing\Portal;
use App\Services\Whistleblowing\WhistleblowingReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Befuellt eine (Test-)Organisation mit synthetischen Hinweisgeberfaellen ueber
 * den echten Meldepfad (Phase 6, Pilot). Verweigert in Produktion ohne --force.
 */
class DemoSeed extends Command {
    protected $signature = 'whistleblowing:demo-seed {organization : Organisations-ID} {--count=5} {--force}';

    protected $description = 'Erzeugt synthetische Hinweisgeberfaelle fuer den Pilotbetrieb.';

    public function handle(WhistleblowingReportService $reports): int {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('In Produktion nur mit --force (synthetische Daten!).');

            return self::FAILURE;
        }
        if ((string) config('whistleblowing.key') === '') {
            $this->error('WHISTLEBLOWING_KEY ist nicht gesetzt.');

            return self::FAILURE;
        }

        $org = Organization::query()->find((int) $this->argument('organization'));
        if ($org === null) {
            $this->error('Organisation nicht gefunden.');

            return self::FAILURE;
        }

        $portal = Portal::query()->withoutGlobalScopes()->firstOrCreate(
            ['organization_id' => $org->id],
            ['public_slug' => 'pilot-' . Str::lower(Str::random(8)), 'is_enabled' => true,
                'allow_anonymous' => true, 'allow_confidential' => true],
        );

        $count = max(1, (int) $this->option('count'));
        $categories = array_map(fn($c) => $c->value, CaseCategory::cases());

        for ($i = 1; $i <= $count; $i++) {
            $result = $reports->submit($portal, [
                'reporter_mode' => $i % 3 === 0 ? ReporterMode::Confidential->value : ReporterMode::Anonymous->value,
                'category' => $categories[array_rand($categories)],
                'subject' => "Synthetischer Testfall {$i}",
                'description' => "Dies ist ein synthetischer Hinweis Nr. {$i} fuer den Pilotbetrieb. Kein echter Inhalt.",
                'contact' => $i % 3 === 0 ? 'pilot@example.test' : null,
            ]);
            $this->line('  angelegt: ' . $result['case_number']);
        }

        $this->info("{$count} synthetische Faelle fuer Organisation {$org->id} (Portal /melden/{$portal->public_slug}).");

        return self::SUCCESS;
    }
}
