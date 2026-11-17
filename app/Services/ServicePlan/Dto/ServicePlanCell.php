<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServicePlanCell.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServicePlan\Dto;

use App\Enums\Diary\Status;
use App\Enums\ServicePlan\ServicePlanCellState;
use App\Support\Period\PeriodKey;
use Carbon\CarbonImmutable;

/**
 * Eine Zelle des Leistungsplans: Kunde × Leistung × Periode. Der Stand kommt
 * aus dem Auftrag der Periode (`orderId` …), sonst aus Leistungsumfang und
 * „entfällt“. Fälligkeit: Auftrag, sonst früheste Kundenfrist (Feature 197),
 * in der Periodensicht sonst das Periodenende.
 */
final readonly class ServicePlanCell {
    public function __construct(
        public int $customerId,
        public ServiceKey $service,
        public PeriodKey $period,
        public ServicePlanCellState $state,
        public bool $mandatory,
        public ?CarbonImmutable $due = null,
        public bool $overdue = false,
        public ?int $responsibleId = null,
        public ?int $orderId = null,
        public ?string $orderTitle = null,
        public ?Status $orderStatus = null,
        public ?int $orderAssigneeId = null,
        public ?string $waiverReason = null,
        public ?int $waiverBy = null,
        public ?CarbonImmutable $waivedAt = null,
        public ?CarbonImmutable $documentsReceivedOn = null,
        public ?string $note = null,
        public ?CarbonImmutable $advanceRequestedOn = null,
        public ?int $deadlineId = null,
        public bool $hidden = false,
        public int $span = 1,
    ) {}

    /** Gilt die Leistung in dieser Periode? */
    public function inScope(): bool {
        return $this->state !== ServicePlanCellState::None;
    }

    /** Zählt in Kacheln und Filtern (sichtbar und im Umfang)? */
    public function counts(): bool {
        return ! $this->hidden && $this->inScope();
    }

    /** Filtergruppe der Zählkacheln einschließlich „überfällig“. */
    public function matches(string $filter): bool {
        if (! $this->counts()) {
            return false;
        }

        return $filter === 'overdue' ? $this->overdue : $this->state->filterGroup() === $filter;
    }
}
