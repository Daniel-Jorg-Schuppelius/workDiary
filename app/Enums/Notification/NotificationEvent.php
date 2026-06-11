<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NotificationEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Notification;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;
use App\Enums\User\UserRole;

/**
 * Ereignistypen-Registry für Benachrichtigungen & Eskalationen (MVP-018).
 *
 * Jeder Case ist im Code real verdrahtet — entweder synchron (Service-Trigger)
 * oder über den Fristen-Scanner (notifications:scan-deadlines). Die
 * default*()-Methoden liefern die Seed-Defaults, die greifen, solange eine
 * Organisation keine eigene NotificationRule-Zeile angelegt hat.
 */
enum NotificationEvent: string implements HasLabel {
    use HasOptions;

    /** Synchron: OpenIssueService::assign()/create() */
    case OpenIssueAssigned = 'openIssue.assigned';
    /** Scanner: OpenIssue mit due_at innerhalb des Vorlaufs */
    case OpenIssueDueSoon = 'openIssue.dueSoon';
    /** Scanner: OpenIssue mit überschrittener due_at */
    case OpenIssueOverdue = 'openIssue.overdue';
    /** Scanner: Kommunikationsnotiz mit fälliger Folgeaktion */
    case CommunicationFollowupDueSoon = 'communication.followupDueSoon';
    /** Scanner: Kommunikationsnotiz mit überfälliger Folgeaktion */
    case CommunicationFollowupOverdue = 'communication.followupOverdue';
    /** Scanner: Dokument läuft innerhalb des Vorlaufs ab */
    case DocumentExpiringSoon = 'document.expiringSoon';
    /** Scanner: Dokument ist abgelaufen */
    case DocumentExpired = 'document.expired';
    /** Synchron: TimeCorrectionService::submit() */
    case TimeCorrectionRequested = 'timeCorrection.requested';
    /** Synchron: TimeCorrectionService::approve()/reject() */
    case TimeCorrectionDecided = 'timeCorrection.decided';
    /** Synchron: MonthClosureService::submit() */
    case MonthClosureSubmitted = 'monthClosure.submitted';

    public function label(): string {
        return (string) __('enums.notification.event.' . $this->value);
    }

    /**
     * Default-Kanäle, solange keine Org-Regel existiert.
     *
     * @return list<string>
     */
    public function defaultChannels(): array {
        return [NotificationChannel::InApp->value, NotificationChannel::Mail->value];
    }

    /**
     * Geht die Benachrichtigung per Default an die betroffene Person?
     * Bei Workflow-Anträgen (requested/submitted) ist die betroffene Person
     * der Antragsteller selbst — Empfänger sind hier die Entscheider (Rolle).
     */
    public function defaultNotifyAffected(): bool {
        return ! in_array($this, [self::TimeCorrectionRequested, self::MonthClosureSubmitted], true);
    }

    /**
     * Default-Empfängerrollen (zusätzlich bzw. statt der betroffenen Person).
     *
     * @return list<string>
     */
    public function defaultRecipientRoles(): array {
        return match ($this) {
            self::TimeCorrectionRequested,
            self::MonthClosureSubmitted => [UserRole::Teamleitung->value],
            default => [],
        };
    }

    /** Material-Symbols-Icon für In-App-/Push-Darstellung. */
    public function icon(): string {
        return match ($this) {
            self::OpenIssueAssigned,
            self::OpenIssueDueSoon,
            self::OpenIssueOverdue => 'assignment_late',
            self::CommunicationFollowupDueSoon,
            self::CommunicationFollowupOverdue => 'forward_to_inbox',
            self::DocumentExpiringSoon,
            self::DocumentExpired => 'folder_open',
            self::TimeCorrectionRequested,
            self::TimeCorrectionDecided => 'edit_calendar',
            self::MonthClosureSubmitted => 'event_available',
        };
    }

    /**
     * Nur für „überfällig"-Ereignisse ist die Eskalations-Stufe sinnvoll
     * (Original unerledigt nach X Stunden → zusätzlich an Rolle).
     */
    public function supportsEscalation(): bool {
        return in_array($this, [
            self::OpenIssueOverdue,
            self::CommunicationFollowupOverdue,
            self::DocumentExpired,
        ], true);
    }
}
