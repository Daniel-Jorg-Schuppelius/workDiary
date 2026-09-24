<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StatusTransitionsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\Asset\{AssetStatus, DefectStatus, MaintenanceWindowStatus};
use App\Enums\Contracts\HasStatusTransitions;
use App\Enums\Gaeb\BoqItemStatus;
use App\Enums\OpenIssue\OpenIssueStatus;
use App\Enums\ServiceTicket\{ProblemStatus, ServiceTicketStatus};
use App\Enums\Tour\TourStatus;
use App\Enums\Whistleblowing\CaseStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * MVP-872: Übergangstabellen liegen im Enum. Je Enum erlaubte und verbotene
 * Wechsel (aus den früheren Dienst-Tabellen) plus Vertragsform.
 */
final class StatusTransitionsTest extends TestCase {
    /** @return array<string, array{HasStatusTransitions, HasStatusTransitions, bool}> */
    public static function transitions(): array {
        return [
            'LV: Entwurf → Auftrag' => [BoqItemStatus::Draft, BoqItemStatus::Ordered, true],
            'LV: Abgeschlossen → Entwurf' => [BoqItemStatus::Completed, BoqItemStatus::Draft, false],
            'Hinweis: eingegangen → bestätigt' => [CaseStatus::Submitted, CaseStatus::Acknowledged, true],
            'Hinweis: eingegangen → gelöscht' => [CaseStatus::Submitted, CaseStatus::Deleted, false],
            'Hinweis: Abschluss → Wiederaufnahme' => [CaseStatus::ClosedDuplicate, CaseStatus::Investigating, true],
            'Offener Punkt: offen → blockiert' => [OpenIssueStatus::Open, OpenIssueStatus::Blocked, false],
            'Offener Punkt: wiedereröffnet → in Arbeit' => [OpenIssueStatus::Reopened, OpenIssueStatus::InProgress, true],
            'Asset: aktiv → in Reparatur' => [AssetStatus::Active, AssetStatus::InRepair, true],
            'Asset: ausgemustert → aktiv' => [AssetStatus::Decommissioned, AssetStatus::Active, false],
            'Defekt: erledigt → offen' => [DefectStatus::Resolved, DefectStatus::Open, true],
            'Defekt: abgeschrieben → offen' => [DefectStatus::WrittenOff, DefectStatus::Open, false],
            'Ticket: gemeldet → gesichtet' => [ServiceTicketStatus::Reported, ServiceTicketStatus::Triaged, true],
            'Ticket: gemeldet → geschlossen' => [ServiceTicketStatus::Reported, ServiceTicketStatus::Closed, false],
            'Ticket: geschlossen → gemeldet' => [ServiceTicketStatus::Closed, ServiceTicketStatus::Reported, false],
            'Ticket: wartet → in Arbeit' => [ServiceTicketStatus::WaitingCustomer, ServiceTicketStatus::InProgress, true],
            'Tour: Entwurf → abgeschlossen' => [TourStatus::Draft, TourStatus::Completed, false],
            'Tour: geplant → abgeschlossen' => [TourStatus::Planned, TourStatus::Completed, true],
            'Problem: offen → Known Error' => [ProblemStatus::Open, ProblemStatus::KnownError, false],
            'Problem: Analyse → Known Error' => [ProblemStatus::Analyzing, ProblemStatus::KnownError, true],
            'Wartungsfenster: geplant → aktiv' => [MaintenanceWindowStatus::Planned, MaintenanceWindowStatus::Active, true],
            'Wartungsfenster: abgeschlossen → aktiv' => [MaintenanceWindowStatus::Completed, MaintenanceWindowStatus::Active, false],
        ];
    }

    #[DataProvider('transitions')]
    public function test_transition_table(HasStatusTransitions $from, HasStatusTransitions $to, bool $allowed): void {
        $this->assertSame($allowed, in_array($to, $from->allowedTransitions(), true));
    }

    /** @return array<string, array{class-string<\BackedEnum&HasStatusTransitions>}> */
    public static function enums(): array {
        return array_map(static fn (string $class): array => [$class], [
            'LV' => BoqItemStatus::class, 'Hinweis' => CaseStatus::class, 'Offener Punkt' => OpenIssueStatus::class,
            'Asset' => AssetStatus::class, 'Defekt' => DefectStatus::class, 'Ticket' => ServiceTicketStatus::class,
            'Tour' => TourStatus::class, 'Problem' => ProblemStatus::class, 'Wartungsfenster' => MaintenanceWindowStatus::class,
        ]);
    }

    /** @param class-string<\BackedEnum&HasStatusTransitions> $class */
    #[DataProvider('enums')]
    public function test_every_case_has_a_duplicate_free_table_without_self_loops(string $class): void {
        foreach ($class::cases() as $case) {
            $targets = $case->allowedTransitions();
            $this->assertNotContains($case, $targets, "{$class}::{$case->name} darf nicht auf sich selbst zeigen");
            $this->assertSame(count($targets), count(array_unique(array_map(static fn (\BackedEnum $t): string|int => $t->value, $targets))));
            $this->assertNotSame('', $case->label());
        }
    }

    public function test_ticket_board_lists_every_status_once(): void {
        $board = ServiceTicketStatus::boardOrder();
        $this->assertCount(count(ServiceTicketStatus::cases()), $board);
        $this->assertSame(ServiceTicketStatus::Reported, $board[0]);
    }
}
