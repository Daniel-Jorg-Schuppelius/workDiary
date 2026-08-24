<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaymentAllocationsSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Gdpdu;

use App\Models\{Expense, Invoice, Organization};
use App\Models\Finance\PaymentAllocation;
use Carbon\CarbonInterface;

/**
 * Zahlungszuordnungen (A16): bestätigte, aktive Zuordnungen zu Bankumsätzen
 * mit Buchungsdatum im Prüfungszeitraum — inkl. Chargeback-Kompensationen
 * (negativer Betrag, Grund im `note`-Feld: `RET#<id> <Grund>`, MVP-334).
 * Aufgehobene Zuordnungen (unmatch = SoftDelete) sind kein Bestand mehr.
 */
class PaymentAllocationsSection extends AbstractGdpduSection {
    public function key(): string {
        return 'payment_allocations';
    }

    public function definition(): array {
        return [
            'file' => 'zahlungszuordnungen.csv',
            'name' => 'Zahlungszuordnungen',
            'description' => 'Bestätigte Zahlungszuordnungen (inkl. Rückläufer-Kompensationen mit Grund) zu Bankumsätzen des Prüfungszeitraums, nach Buchungsdatum des Umsatzes.',
            'columns' => [
                ['name' => 'Buchungsdatum', 'type' => 'date'],
                ['name' => 'Bank_Betrag', 'type' => 'numeric', 'accuracy' => 2],
                ['name' => 'Waehrung', 'type' => 'alpha'],
                ['name' => 'Referenz', 'type' => 'alpha'],
                ['name' => 'Art', 'type' => 'alpha'],
                ['name' => 'Betrag', 'type' => 'numeric', 'accuracy' => 2],
                ['name' => 'Belegtyp', 'type' => 'alpha'],
                ['name' => 'Beleg', 'type' => 'alpha'],
                ['name' => 'Zugeordnet_am', 'type' => 'alpha'],
                ['name' => 'Grund', 'type' => 'alpha'],
            ],
        ];
    }

    public function rows(Organization $organization, CarbonInterface $from, CarbonInterface $to): array {
        $rows = [];
        PaymentAllocation::query()
            ->where('payment_allocations.organization_id', $organization->id)
            ->whereHas('transaction', fn ($q) => $q->whereBetween('booking_date', [$from->toDateString(), $to->toDateString()]))
            ->with(['transaction', 'allocatable'])
            ->orderBy('id')
            ->get()
            ->each(function (PaymentAllocation $allocation) use (&$rows): void {
                $tx = $allocation->transaction;
                $allocatable = $allocation->allocatable;
                $document = match (true) {
                    $allocatable instanceof Invoice => $this->str($allocatable->number),
                    $allocatable instanceof Expense => 'E-' . $allocatable->id,
                    default => '',
                };
                $rows[] = [
                    $this->date($tx?->booking_date),
                    $this->num($tx?->signedAmount(), 2),
                    $this->str($tx?->currency->value),
                    $this->str($tx?->end_to_end_id),
                    $this->str($allocation->kind->value),
                    $this->num($allocation->amount, 2),
                    $allocatable !== null ? class_basename($allocatable) : '',
                    $document,
                    $this->dateTime($allocation->confirmed_at),
                    $this->str($allocation->note),
                ];
            });

        return $rows;
    }
}
