<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Claims;

/**
 * Statusmodell der Reklamationsakte (Feature 072, MVP-246): Eingang →
 * Bewertung → Entscheidung → Umsetzung → Abschluss; Ablehnung/Rückzug
 * sind terminale Seitenausgänge. Übergänge prüft ClaimCaseService.
 */
enum ClaimStatus: string {
    case Received = 'received';
    case Assessing = 'assessing';
    case Decided = 'decided';
    case InProgress = 'in_progress';
    case Closed = 'closed';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string {
        return match ($this) {
            self::Received => (string) __('Eingegangen'),
            self::Assessing => (string) __('In Bewertung'),
            self::Decided => (string) __('Entschieden'),
            self::InProgress => (string) __('In Umsetzung'),
            self::Closed => (string) __('Abgeschlossen'),
            self::Rejected => (string) __('Abgelehnt'),
            self::Withdrawn => (string) __('Zurückgezogen'),
        };
    }

    public function isOpen(): bool {
        return ! in_array($this, [self::Closed, self::Rejected, self::Withdrawn], true);
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Received => [self::Assessing, self::Rejected, self::Withdrawn],
            self::Assessing => [self::Decided, self::Rejected, self::Withdrawn],
            self::Decided => [self::InProgress, self::Closed, self::Rejected],
            self::InProgress => [self::Closed],
            self::Closed, self::Rejected, self::Withdrawn => [],
        };
    }
}
