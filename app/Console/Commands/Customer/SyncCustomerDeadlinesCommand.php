<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SyncCustomerDeadlinesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Customer;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\Platform\Organization;
use App\Services\Customer\Deadlines\CustomerDeadlineGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Jahreslauf der Kundenfristen (Feature 197, MVP-1632): erzeugt je
 * Organisation die Fristen des Vorjahres, des laufenden und des Folgejahres
 * aus Fristmerkmalen und Leistungsumfang und rechnet offene neu. Idempotent —
 * läuft monatlich, damit das Folgejahr rechtzeitig steht und ein verpasster
 * Lauf nachgeholt wird. Kundenmodul ist Kern: kein Modul-Gate; welche
 * Regelsätze laufen, filtert `ModuleExtensions::activeFor()` je Organisation.
 */
class SyncCustomerDeadlinesCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'customer-deadlines:sync {--organization= : Nur diese Organisation (ID)} {--date= : Stichtag (Standard: heute)}';

    protected $description = 'Erzeugt und aktualisiert die Kundenfristen aus den Regelsätzen (Feature 197)';

    public function handle(CustomerDeadlineGenerator $generator): int {
        $today = CarbonImmutable::parse((string) ($this->option('date') ?? 'today'))->startOfDay();
        $totals = ['created' => 0, 'updated' => 0, 'removed' => 0];

        $failures = $this->forEachOrganization(function (Organization $organization) use ($generator, $today, &$totals): void {
            foreach ($generator->syncOrganization($organization, $today) as $key => $count) {
                $totals[$key] += $count;
            }
        });

        $this->info(sprintf('%d Fristen angelegt, %d aktualisiert, %d entfernt.', $totals['created'], $totals['updated'], $totals['removed']));

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
