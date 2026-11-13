<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServicePlanRow.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServicePlan\Dto;

/**
 * Zeile des Leistungsplans: in der Jahressicht ein Kunde (Zellen je Leistung),
 * in der Periodensicht Kunde × Leistung (Zellen je Periode, `service`).
 */
final readonly class ServicePlanRow {
    /**
     * @param  array<string, ServicePlanCell>  $cells  Jahressicht: Schlüssel der Leistung; Periodensicht: Periodenschlüssel
     */
    public function __construct(
        public int $customerId,
        public string $customerName,
        public ?string $customerNumber,
        public array $cells,
        public ?ServicePlanColumn $service = null,
        public ?int $responsibleId = null,
    ) {}

    public function key(): string {
        return $this->customerId . ($this->service !== null ? '|' . $this->service->service->key() . '|' . $this->service->kind->value : '');
    }

    public function hasVisibleCell(): bool {
        foreach ($this->cells as $cell) {
            if ($cell->counts()) {
                return true;
            }
        }

        return false;
    }
}
