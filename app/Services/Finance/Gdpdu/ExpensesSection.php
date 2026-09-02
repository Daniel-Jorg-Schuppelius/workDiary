<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpensesSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Gdpdu;

use App\Enums\Expense\ExpenseStatus;
use App\Models\{Expense, Organization};
use App\Support\Query\DateRange;
use Carbon\CarbonInterface;

/**
 * Freigegebene Spesen (A16): nur Belege, deren Freigabe erteilt wurde
 * (approved/reimbursed/invoiced) — Entwürfe, offene und abgelehnte Belege
 * sind steuerlich kein Aufwand und bleiben außen vor. Freigabe-Person als
 * ID (Datenminimierung), Zahlungsstatus über `Erstattet_am`.
 */
class ExpensesSection extends AbstractGdpduSection {
    public function key(): string {
        return 'expenses';
    }

    public function definition(): array {
        return [
            'file' => 'spesen.csv',
            'name' => 'Spesen',
            'description' => 'Freigegebene Spesen/Auslagen des Prüfungszeitraums (nach Belegdatum); Entwürfe, offene und abgelehnte Belege sind nicht enthalten.',
            'columns' => [
                ['name' => 'Belegnummer', 'type' => 'alpha'],
                ['name' => 'Datum', 'type' => 'date'],
                ['name' => 'Kategorie', 'type' => 'alpha'],
                ['name' => 'Lieferant', 'type' => 'alpha'],
                ['name' => 'Beschreibung', 'type' => 'alpha'],
                ['name' => 'Waehrung', 'type' => 'alpha'],
                ['name' => 'Netto', 'type' => 'numeric', 'accuracy' => 2],
                ['name' => 'USt_Satz', 'type' => 'numeric', 'accuracy' => 2],
                ['name' => 'USt_Betrag', 'type' => 'numeric', 'accuracy' => 2],
                ['name' => 'Brutto', 'type' => 'numeric', 'accuracy' => 2],
                ['name' => 'Status', 'type' => 'alpha'],
                ['name' => 'Freigegeben_am', 'type' => 'alpha'],
                ['name' => 'Freigegeben_von', 'type' => 'alpha'],
                ['name' => 'Erstattet_am', 'type' => 'alpha'],
            ],
        ];
    }

    public function rows(Organization $organization, CarbonInterface $from, CarbonInterface $to): iterable {
        foreach (Expense::query()
            ->where('organization_id', $organization->id)
            ->whereBetween('date', DateRange::days($from, $to))
            ->whereIn('status', [ExpenseStatus::Approved->value, ExpenseStatus::Reimbursed->value, ExpenseStatus::Invoiced->value])
            ->with('category:id,label')
            ->orderBy('date')->orderBy('id')
            ->lazy() as $expense) {
            yield [
                'E-' . $expense->id,
                $this->date($expense->date),
                $this->str($expense->category?->label),
                $this->str($expense->vendor),
                $this->str($expense->description),
                $this->str($expense->currency->value),
                $this->num($expense->amount_net?->toFloat(), 2),
                $this->num($expense->tax_rate !== null ? (float) $expense->tax_rate->getNumericValue() : null, 2),
                $this->num($expense->tax_amount?->toFloat(), 2),
                $this->num($expense->amount_gross?->toFloat(), 2),
                $this->str($expense->status->value),
                $this->dateTime($expense->decided_at),
                $this->str($expense->decided_by),
                $this->dateTime($expense->reimbursed_at),
            ];
        }
    }
}
