<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingEInvoicesSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Gdpdu;

use App\Models\{IncomingEInvoice, Organization};
use Carbon\CarbonInterface;

/**
 * Eingangs-E-Rechnungen (Vollaudit 2026-07, H11): Formatmetadaten des
 * Eingangskanals — SHA-256, Quelle, Status, Übergabe (066→063).
 */
class IncomingEInvoicesSection extends AbstractGdpduSection {
    public function key(): string {
        return 'incoming_einvoices';
    }

    public function definition(): array {
        return [
            'file' => 'eingangsrechnungen.csv',
            'name' => 'Eingangs-E-Rechnungen',
            'description' => 'Eingegangene E-Rechnungen des Prüfungszeitraums (nach Eingangsdatum) mit SHA-256, Quelle, Validierungs-/Freigabestatus und Übergabezeitpunkt.',
            'columns' => [
                ['name' => 'Eingegangen_am', 'type' => 'alpha'],
                ['name' => 'Quelle', 'type' => 'alpha'],
                ['name' => 'Status', 'type' => 'alpha'],
                ['name' => 'SHA256', 'type' => 'alpha'],
                ['name' => 'Entschieden_am', 'type' => 'alpha'],
                ['name' => 'Entscheidungsnotiz', 'type' => 'alpha'],
                ['name' => 'Uebergeben_am', 'type' => 'alpha'],
            ],
        ];
    }

    public function rows(Organization $organization, CarbonInterface $from, CarbonInterface $to): iterable {
        foreach (IncomingEInvoice::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereBetween('received_at', [$from->toDateString() . ' 00:00:00', $to->toDateString() . ' 23:59:59'])
            ->orderBy('received_at')->orderBy('id')
            ->lazy() as $incoming) {
            yield [
                $this->dateTime($incoming->received_at),
                $this->str($incoming->source),
                $this->str($incoming->status),
                $this->str($incoming->sha256),
                $this->dateTime($incoming->decided_at),
                $this->str($incoming->decision_note),
                $this->dateTime($incoming->transferred_at),
            ];
        }
    }
}
