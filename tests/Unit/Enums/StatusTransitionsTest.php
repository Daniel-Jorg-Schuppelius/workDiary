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

use App\Enums\Agile\AgileSprintStatus;
use App\Enums\Ai\AiTextSuggestionStatus;
use App\Enums\Applications\{ApplicationContractNegotiationStatus, ApplicationOpportunityStatus, EmployeeDraftStatus, JobPostingStatus};
use App\Enums\Asset\{AssetStatus, DefectStatus, MaintenanceWindowStatus};
use App\Enums\Calendar\AppointmentRequestStatus;
use App\Enums\Contracts\HasStatusTransitions;
use App\Enums\Crisis\{CrisisCaseStatus, CrisisCommunicationStatus};
use App\Enums\Gaeb\BoqItemStatus;
use App\Enums\Integration\IntegrationInboxStatus;
use App\Enums\Inventory\StockLotStatus;
use App\Enums\Investments\{InvestmentBudgetRequestStatus, InvestmentDeviationStatus};
use App\Enums\Invoicing\IncomingEInvoiceStatus;
use App\Enums\Learning\LearningTimeApprovalStatus;
use App\Enums\OpenIssue\OpenIssueStatus;
use App\Enums\Patrol\PatrolRunStatus;
use App\Enums\Sales\QuoteStatus;
use App\Enums\ServiceTicket\{ChangeStatus, ProblemStatus, ServiceTicketStatus};
use App\Enums\Sustainability\SustainabilityAssessmentStatus;
use App\Enums\Tenders\TenderNoticeMatchState;
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
            'Sprint: geplant → aktiv' => [AgileSprintStatus::Planned, AgileSprintStatus::Active, true],
            'Sprint: geplant → abgeschlossen' => [AgileSprintStatus::Planned, AgileSprintStatus::Completed, false],
            'Sprint: aktiv → abgebrochen' => [AgileSprintStatus::Active, AgileSprintStatus::Cancelled, true],
            'Sprint: abgeschlossen → aktiv' => [AgileSprintStatus::Completed, AgileSprintStatus::Active, false],
            'Terminwunsch: angefragt → bestätigt' => [AppointmentRequestStatus::Requested, AppointmentRequestStatus::Confirmed, true],
            'Terminwunsch: abgelehnt → bestätigt' => [AppointmentRequestStatus::Declined, AppointmentRequestStatus::Confirmed, false],
            'Terminwunsch: abgelehnt → storniert (Calendly)' => [AppointmentRequestStatus::Declined, AppointmentRequestStatus::Canceled, true],
            'Terminwunsch: storniert → ersetzt' => [AppointmentRequestStatus::Canceled, AppointmentRequestStatus::Superseded, false],
            'KI-Vorschlag: offen → geändert übernommen' => [AiTextSuggestionStatus::Proposed, AiTextSuggestionStatus::Edited, true],
            'KI-Vorschlag: verfallen → übernommen' => [AiTextSuggestionStatus::Expired, AiTextSuggestionStatus::Accepted, false],
            'Eingangsrechnung: empfangen → Zahlung' => [IncomingEInvoiceStatus::Received, IncomingEInvoiceStatus::PaymentReleased, false],
            'Eingangsrechnung: Rückfrage → freigegeben' => [IncomingEInvoiceStatus::Question, IncomingEInvoiceStatus::Approved, true],
            'Eingangsrechnung: freigegeben → Zahlung' => [IncomingEInvoiceStatus::Approved, IncomingEInvoiceStatus::PaymentReleased, true],
            'Eingangsrechnung: freigegeben → Rückfrage' => [IncomingEInvoiceStatus::Approved, IncomingEInvoiceStatus::Question, false],
            'Eingangsrechnung: abgelehnt → freigegeben' => [IncomingEInvoiceStatus::Rejected, IncomingEInvoiceStatus::Approved, false],
            'Inbox: offen → zugeordnet' => [IntegrationInboxStatus::Open, IntegrationInboxStatus::ResolvedLinked, true],
            'Inbox: verworfen → offen (erneut gemeldet)' => [IntegrationInboxStatus::Dismissed, IntegrationInboxStatus::Open, true],
            'Inbox: zugeordnet → verworfen' => [IntegrationInboxStatus::ResolvedLinked, IntegrationInboxStatus::Dismissed, false],
            'Vertragsverhandlung: freigegeben → abgeschlossen' => [ApplicationContractNegotiationStatus::Approved, ApplicationContractNegotiationStatus::Concluded, true],
            'Vertragsverhandlung: in Prüfung → abgeschlossen' => [ApplicationContractNegotiationStatus::InReview, ApplicationContractNegotiationStatus::Concluded, false],
            'Vertragsverhandlung: Entwurf → abgelehnt' => [ApplicationContractNegotiationStatus::Draft, ApplicationContractNegotiationStatus::Declined, true],
            'Vertragsverhandlung: freigegeben → Gegenentwurf (neue Version)' => [ApplicationContractNegotiationStatus::Approved, ApplicationContractNegotiationStatus::Counter, true],
            'Vertragsverhandlung: abgeschlossen → in Prüfung' => [ApplicationContractNegotiationStatus::Concluded, ApplicationContractNegotiationStatus::InReview, false],
            'Ausschreibung: erfasst → gewonnen' => [ApplicationOpportunityStatus::Captured, ApplicationOpportunityStatus::Won, true],
            'Ausschreibung: nachgefordert → in Bearbeitung' => [ApplicationOpportunityStatus::PostSubmission, ApplicationOpportunityStatus::InProgress, true],
            'Ausschreibung: eingereicht → zurückgezogen' => [ApplicationOpportunityStatus::Submitted, ApplicationOpportunityStatus::Withdrawn, true],
            'Ausschreibung: gewonnen → verloren' => [ApplicationOpportunityStatus::Won, ApplicationOpportunityStatus::Lost, false],
            'Ausschreibung: zurückgezogen → in Bearbeitung' => [ApplicationOpportunityStatus::Withdrawn, ApplicationOpportunityStatus::InProgress, false],
            'Ausschreibung: offen → archiviert (keine Schreibstelle)' => [ApplicationOpportunityStatus::InProgress, ApplicationOpportunityStatus::Archived, false],
            'Mitarbeiter-Entwurf: Entwurf → eingeladen' => [EmployeeDraftStatus::Draft, EmployeeDraftStatus::Invited, true],
            'Mitarbeiter-Entwurf: eingeladen → Entwurf' => [EmployeeDraftStatus::Invited, EmployeeDraftStatus::Draft, false],
            'Mitarbeiter-Entwurf: verworfen → eingeladen' => [EmployeeDraftStatus::Discarded, EmployeeDraftStatus::Invited, false],
            'Stellenanzeige: veröffentlicht → pausiert' => [JobPostingStatus::Published, JobPostingStatus::Paused, true],
            'Stellenanzeige: Entwurf → pausiert' => [JobPostingStatus::Draft, JobPostingStatus::Paused, false],
            'Stellenanzeige: geschlossen → pausiert' => [JobPostingStatus::Closed, JobPostingStatus::Paused, false],
            'Stellenanzeige: geschlossen → veröffentlicht (Karriereseite erneut)' => [JobPostingStatus::Closed, JobPostingStatus::Published, true],
            'Stellenanzeige: veröffentlicht → abgelaufen (Tageslauf)' => [JobPostingStatus::Published, JobPostingStatus::Expired, true],
            'Stellenanzeige: pausiert → abgelaufen' => [JobPostingStatus::Paused, JobPostingStatus::Expired, false],
            'Stellenanzeige: Entwurf → abgelaufen' => [JobPostingStatus::Draft, JobPostingStatus::Expired, false],
            'Stellenanzeige: geschlossen → abgelaufen' => [JobPostingStatus::Closed, JobPostingStatus::Expired, false],
            'Stellenanzeige: abgelaufen → veröffentlicht (neues Datum)' => [JobPostingStatus::Expired, JobPostingStatus::Published, true],
            'Stellenanzeige: abgelaufen → geschlossen' => [JobPostingStatus::Expired, JobPostingStatus::Closed, true],
            'Stellenanzeige: abgelaufen → pausiert' => [JobPostingStatus::Expired, JobPostingStatus::Paused, false],
            'Radar-Treffer: neu → ausgeblendet' => [TenderNoticeMatchState::New, TenderNoticeMatchState::Muted, true],
            'Radar-Treffer: ausgeblendet → neu' => [TenderNoticeMatchState::Muted, TenderNoticeMatchState::New, true],
            'Radar-Treffer: ausgeblendet → übernommen' => [TenderNoticeMatchState::Muted, TenderNoticeMatchState::Converted, true],
            'Radar-Treffer: übernommen → neu' => [TenderNoticeMatchState::Converted, TenderNoticeMatchState::New, false],
            'Krise: gemeldet → aktiviert' => [CrisisCaseStatus::Reported, CrisisCaseStatus::Activated, true],
            'Krise: bewertet → aktiviert' => [CrisisCaseStatus::Assessed, CrisisCaseStatus::Activated, true],
            'Krise: stabilisiert → aktiviert (ohne Alarm begonnene Akte; ob schon aktiviert, prüft die Akte)' => [CrisisCaseStatus::Stabilized, CrisisCaseStatus::Activated, true],
            'Krise: vorbereitet → aktiviert (keine Schreibstelle)' => [CrisisCaseStatus::Prepared, CrisisCaseStatus::Activated, false],
            'Krise: Wiederanlauf → entwarnt' => [CrisisCaseStatus::Recovery, CrisisCaseStatus::AllClear, true],
            'Krise: vorbereitet → entwarnt' => [CrisisCaseStatus::Prepared, CrisisCaseStatus::AllClear, false],
            'Krise: geschlossen → entwarnt' => [CrisisCaseStatus::Closed, CrisisCaseStatus::AllClear, false],
            'Krise: entwarnt → nachbereitet' => [CrisisCaseStatus::AllClear, CrisisCaseStatus::PostReview, true],
            'Krise: aktiviert → nachbereitet' => [CrisisCaseStatus::Activated, CrisisCaseStatus::PostReview, false],
            'Krise: nachbereitet → geschlossen' => [CrisisCaseStatus::PostReview, CrisisCaseStatus::Closed, true],
            'Krise: gemeldet → bewertet (Lagezustand in der aktiven Akte)' => [CrisisCaseStatus::Reported, CrisisCaseStatus::Assessed, true],
            'Krise: aktiviert → stabilisiert' => [CrisisCaseStatus::Activated, CrisisCaseStatus::Stabilized, true],
            'Krise: aktiviert → bewertet (einmal aktiviert, nie wieder bewertet)' => [CrisisCaseStatus::Activated, CrisisCaseStatus::Assessed, false],
            'Krise: in Bearbeitung → bewertet' => [CrisisCaseStatus::InProgress, CrisisCaseStatus::Assessed, false],
            'Krise: Wiederanlauf → bewertet' => [CrisisCaseStatus::Recovery, CrisisCaseStatus::Assessed, false],
            'Krise: bewertet → in Bearbeitung' => [CrisisCaseStatus::Assessed, CrisisCaseStatus::InProgress, true],
            'Krise: stabilisiert → in Bearbeitung (Lagezustände bleiben wechselbar)' => [CrisisCaseStatus::Stabilized, CrisisCaseStatus::InProgress, true],
            'Krise: entwarnt → Wiederanlauf (Lagezustände nur in der aktiven Akte)' => [CrisisCaseStatus::AllClear, CrisisCaseStatus::Recovery, false],
            'Krise: nachbereitet → in Bearbeitung' => [CrisisCaseStatus::PostReview, CrisisCaseStatus::InProgress, false],
            'Krise: geschlossen → in Bearbeitung (Endzustand)' => [CrisisCaseStatus::Closed, CrisisCaseStatus::InProgress, false],
            'Krise: verworfen → bewertet (Endzustand)' => [CrisisCaseStatus::Discarded, CrisisCaseStatus::Assessed, false],
            'Krise: vorbereitet → bewertet (keine Schreibstelle)' => [CrisisCaseStatus::Prepared, CrisisCaseStatus::Assessed, false],
            'Krise: aktiviert → geschlossen (erst Entwarnung und Nachbereitung)' => [CrisisCaseStatus::Activated, CrisisCaseStatus::Closed, false],
            'Krise: entwarnt → geschlossen (erst Nachbereitung)' => [CrisisCaseStatus::AllClear, CrisisCaseStatus::Closed, false],
            'Krise: gemeldet → verworfen (keine Schreibstelle)' => [CrisisCaseStatus::Reported, CrisisCaseStatus::Discarded, false],
            'Krise: gemeldet → vorbereitet (keine Schreibstelle)' => [CrisisCaseStatus::Reported, CrisisCaseStatus::Prepared, false],
            'Krisenkommunikation: Entwurf → freigegeben' => [CrisisCommunicationStatus::Draft, CrisisCommunicationStatus::Approved, true],
            'Krisenkommunikation: Entwurf → gesendet' => [CrisisCommunicationStatus::Draft, CrisisCommunicationStatus::Sent, false],
            'Krisenkommunikation: freigegeben → gesendet' => [CrisisCommunicationStatus::Approved, CrisisCommunicationStatus::Sent, true],
            'Krisenkommunikation: gesendet → freigegeben' => [CrisisCommunicationStatus::Sent, CrisisCommunicationStatus::Approved, false],
            'Budgetantrag: in Freigabe → genehmigt' => [InvestmentBudgetRequestStatus::InApproval, InvestmentBudgetRequestStatus::Approved, true],
            'Budgetantrag: in Freigabe → abgelehnt' => [InvestmentBudgetRequestStatus::InApproval, InvestmentBudgetRequestStatus::Rejected, true],
            'Budgetantrag: genehmigt → ersetzt (Nachtrag)' => [InvestmentBudgetRequestStatus::Approved, InvestmentBudgetRequestStatus::Superseded, true],
            'Budgetantrag: genehmigt → abgelehnt' => [InvestmentBudgetRequestStatus::Approved, InvestmentBudgetRequestStatus::Rejected, false],
            'Budgetantrag: Entwurf → genehmigt (keine Schreibstelle)' => [InvestmentBudgetRequestStatus::Draft, InvestmentBudgetRequestStatus::Approved, false],
            'Budgetantrag: abgelehnt → in Freigabe' => [InvestmentBudgetRequestStatus::Rejected, InvestmentBudgetRequestStatus::InApproval, false],
            'Abweichung: offen → genehmigt' => [InvestmentDeviationStatus::Open, InvestmentDeviationStatus::Approved, true],
            'Abweichung: offen → abgelehnt' => [InvestmentDeviationStatus::Open, InvestmentDeviationStatus::Rejected, true],
            'Abweichung: genehmigt → abgelehnt' => [InvestmentDeviationStatus::Approved, InvestmentDeviationStatus::Rejected, false],
            'Abweichung: abgelehnt → offen' => [InvestmentDeviationStatus::Rejected, InvestmentDeviationStatus::Open, false],
            'ESG-Bewertung: Entwurf → final' => [SustainabilityAssessmentStatus::Draft, SustainabilityAssessmentStatus::Final, true],
            'ESG-Bewertung: final → Entwurf (neue Version statt Rückweg)' => [SustainabilityAssessmentStatus::Final, SustainabilityAssessmentStatus::Draft, false],
            'Change: wartet auf Freigabe → genehmigt' => [ChangeStatus::PendingApproval, ChangeStatus::Approved, true],
            'Change: wartet auf Freigabe → abgebrochen' => [ChangeStatus::PendingApproval, ChangeStatus::Cancelled, true],
            'Change: genehmigt → in Umsetzung' => [ChangeStatus::Approved, ChangeStatus::Implementing, true],
            'Change: genehmigt → abgeschlossen (ohne Verfahrenslauf)' => [ChangeStatus::Approved, ChangeStatus::Done, true],
            'Change: in Umsetzung → abgeschlossen' => [ChangeStatus::Implementing, ChangeStatus::Done, true],
            'Change: wartet auf Freigabe → in Umsetzung' => [ChangeStatus::PendingApproval, ChangeStatus::Implementing, false],
            'Change: wartet auf Freigabe → abgeschlossen' => [ChangeStatus::PendingApproval, ChangeStatus::Done, false],
            'Change: Entwurf → in Umsetzung (keine Schreibstelle)' => [ChangeStatus::Draft, ChangeStatus::Implementing, false],
            'Change: abgeschlossen → in Umsetzung' => [ChangeStatus::Done, ChangeStatus::Implementing, false],
            'Change: abgebrochen → abgeschlossen' => [ChangeStatus::Cancelled, ChangeStatus::Done, false],
            'Angebot: Entwurf → freigegeben' => [QuoteStatus::Draft, QuoteStatus::Approved, true],
            'Angebot: Entwurf → versandt' => [QuoteStatus::Draft, QuoteStatus::Sent, false],
            'Angebot: freigegeben → versandt' => [QuoteStatus::Approved, QuoteStatus::Sent, true],
            'Angebot: freigegeben → angenommen' => [QuoteStatus::Approved, QuoteStatus::Accepted, false],
            'Angebot: freigegeben → abgelaufen (Bindefrist)' => [QuoteStatus::Approved, QuoteStatus::Expired, true],
            'Angebot: versandt → angenommen' => [QuoteStatus::Sent, QuoteStatus::Accepted, true],
            'Angebot: versandt → teilweise angenommen' => [QuoteStatus::Sent, QuoteStatus::PartiallyAccepted, true],
            'Angebot: versandt → abgelehnt' => [QuoteStatus::Sent, QuoteStatus::Rejected, true],
            'Angebot: versandt → abgelaufen' => [QuoteStatus::Sent, QuoteStatus::Expired, true],
            'Angebot: versandt → Entwurf (neue Version statt Rückweg)' => [QuoteStatus::Sent, QuoteStatus::Draft, false],
            'Angebot: angenommen → abgelehnt' => [QuoteStatus::Accepted, QuoteStatus::Rejected, false],
            'Angebot: abgelehnt → angenommen' => [QuoteStatus::Rejected, QuoteStatus::Accepted, false],
            'Angebot: abgelaufen → versandt' => [QuoteStatus::Expired, QuoteStatus::Sent, false],
            'Charge: aktiv → gesperrt' => [StockLotStatus::Active, StockLotStatus::Blocked, true],
            'Charge: gesperrt → aktiv (Freigabe)' => [StockLotStatus::Blocked, StockLotStatus::Active, true],
            'Charge: aktiv → zusammengeführt' => [StockLotStatus::Active, StockLotStatus::Merged, true],
            'Charge: gesperrt → zusammengeführt (erst freigeben)' => [StockLotStatus::Blocked, StockLotStatus::Merged, false],
            'Charge: zusammengeführt → gesperrt' => [StockLotStatus::Merged, StockLotStatus::Blocked, false],
            'Charge: zusammengeführt → aktiv' => [StockLotStatus::Merged, StockLotStatus::Active, false],
            'Rundgang: laufend → abgeschlossen' => [PatrolRunStatus::Running, PatrolRunStatus::Completed, true],
            'Rundgang: laufend → abgebrochen' => [PatrolRunStatus::Running, PatrolRunStatus::Aborted, true],
            'Rundgang: abgebrochen → abgeschlossen' => [PatrolRunStatus::Aborted, PatrolRunStatus::Completed, false],
            'Rundgang: abgeschlossen → abgebrochen' => [PatrolRunStatus::Completed, PatrolRunStatus::Aborted, false],
            'Rundgang: abgebrochen → laufend' => [PatrolRunStatus::Aborted, PatrolRunStatus::Running, false],
            'Lernzeit: ausstehend → freigegeben' => [LearningTimeApprovalStatus::Pending, LearningTimeApprovalStatus::Approved, true],
            'Lernzeit: ausstehend → abgelehnt' => [LearningTimeApprovalStatus::Pending, LearningTimeApprovalStatus::Rejected, true],
            'Lernzeit: abgelehnt → freigegeben' => [LearningTimeApprovalStatus::Rejected, LearningTimeApprovalStatus::Approved, false],
            'Lernzeit: freigegeben → abgelehnt' => [LearningTimeApprovalStatus::Approved, LearningTimeApprovalStatus::Rejected, false],
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
            'Sprint' => AgileSprintStatus::class, 'Terminwunsch' => AppointmentRequestStatus::class, 'KI-Vorschlag' => AiTextSuggestionStatus::class,
            'Eingangsrechnung' => IncomingEInvoiceStatus::class, 'Inbox' => IntegrationInboxStatus::class,
            'Vertragsverhandlung' => ApplicationContractNegotiationStatus::class, 'Ausschreibung' => ApplicationOpportunityStatus::class,
            'Mitarbeiter-Entwurf' => EmployeeDraftStatus::class, 'Stellenanzeige' => JobPostingStatus::class, 'Radar-Treffer' => TenderNoticeMatchState::class,
            'Krise' => CrisisCaseStatus::class, 'Krisenkommunikation' => CrisisCommunicationStatus::class,
            'Budgetantrag' => InvestmentBudgetRequestStatus::class, 'Abweichung' => InvestmentDeviationStatus::class,
            'ESG-Bewertung' => SustainabilityAssessmentStatus::class,
            'Change' => ChangeStatus::class, 'Angebot' => QuoteStatus::class,
            'Charge' => StockLotStatus::class, 'Rundgang' => PatrolRunStatus::class,
            'Lernzeit-Freigabe' => LearningTimeApprovalStatus::class,
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
