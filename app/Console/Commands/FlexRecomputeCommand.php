<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FlexRecomputeCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\Platform\{Organization, User};
use App\Modules\Attributes\BelongsToModule;
use App\Services\Flextime\FlexCalculator;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Schreibt die gespeicherten Gleitzeitsalden (MVP-1550). Kachel, Terminal,
 * Prognose und Personalbericht lesen `flex_balances`; ohne diesen Lauf bleibt
 * die Tabelle leer. Standard: Vormonat und laufender Monat — der Vormonat,
 * weil Nachträge und Korrekturen dort noch üblich sind.
 */
#[BelongsToModule('time')]
class FlexRecomputeCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'flex:recompute {--from= : Erster Monat (JJJJ-MM) für eine Nachberechnung, sonst der Vormonat} ' . self::ORGANIZATION_OPTION;

    protected $description = 'Berechnet die gespeicherten Gleitzeitsalden je Person neu';

    public function handle(FlexCalculator $calculator): int {
        $from = $this->fromMonth();
        if ($from === false) {
            $this->error('Ungültiger Monat — erwartet wird JJJJ-MM.');

            return self::FAILURE;
        }

        $months = 0;
        $failures = $this->forEachOrganization(function (Organization $organization) use ($calculator, $from, &$months): void {
            $start = $from ?? CarbonImmutable::now(Tz::ofOrganization($organization))->startOfMonth()->subMonth();
            User::query()
                ->where('organization_id', $organization->id)
                ->whereNull('deactivated_at')
                ->orderBy('id')
                ->chunkById(100, function ($users) use ($calculator, $start, &$months): void {
                    foreach ($users as $user) {
                        $months += $calculator->recomputeFrom($user, $start->year, $start->month);
                    }
                });
        });

        $this->info(sprintf('Gleitzeitsalden geschrieben: %d Monatszeile(n).', $months));

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function fromMonth(): CarbonImmutable|false|null {
        $raw = $this->option('from');
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $raw, $m) !== 1) {
            return false;
        }

        return CarbonImmutable::createFromDate((int) $m[1], (int) $m[2], 1)->startOfMonth();
    }
}
