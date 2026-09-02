<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LedgerPeriodsSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Gdpdu;

use App\Models\Accounting\AccountingPeriod;
use App\Models\Organization;
use App\Support\Query\DateRange;
use Carbon\CarbonInterface;

/** Buchungsperioden mit Abschluss- und Wiedereröffnungsnachweis. */
class LedgerPeriodsSection extends AbstractGdpduSection {
    public function key(): string {
        return 'ledger_periods';
    }

    public function definition(): array {
        return [
            'file' => 'buchungsperioden.csv',
            'name' => 'Buchungsperioden',
            'description' => 'Perioden der lokalen Buchhaltung mit Abschluss- und Wiedereröffnungsnachweis.',
            'columns' => [
                ['name' => 'Geschaeftsjahr', 'type' => 'alpha'],
                ['name' => 'Von', 'type' => 'date'],
                ['name' => 'Bis', 'type' => 'date'],
                ['name' => 'Status', 'type' => 'alpha'],
                ['name' => 'Geschlossen_am', 'type' => 'alpha'],
                ['name' => 'Wiedereroeffnet_am', 'type' => 'alpha'],
                ['name' => 'Begruendung', 'type' => 'alpha'],
            ],
        ];
    }

    public function rows(Organization $organization, CarbonInterface $from, CarbonInterface $to): iterable {
        foreach (AccountingPeriod::query()
            ->where('organization_id', $organization->id)
            ->where('starts_on', '<', DateRange::dayAfter($to))
            ->where('ends_on', '>=', DateRange::day($from))
            ->with('fiscalYear')
            ->orderBy('starts_on')->orderBy('id')
            ->lazy() as $period) {
            yield [
                $this->str($period->fiscalYear?->label),
                $this->date($period->starts_on),
                $this->date($period->ends_on),
                $this->str($period->status->value),
                $this->dateTime($period->closed_at),
                $this->dateTime($period->reopened_at),
                $this->str($period->reopen_reason),
            ];
        }
    }
}
