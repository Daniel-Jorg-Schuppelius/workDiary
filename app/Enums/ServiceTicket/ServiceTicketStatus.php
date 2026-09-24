<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceTicketStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\ServiceTicket;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

enum ServiceTicketStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Reported = 'reported';
    case Triaged = 'triaged';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Accepted = 'accepted';
    case Closed = 'closed';
    case Rejected = 'rejected';
        // Wartezustände (Feature 065, additiv — 'done' bleibt der
        // Speicherwert für „Gelöst", nur das Label ändert sich).
    case WaitingCustomer = 'waiting_customer';
    case WaitingExternal = 'waiting_external';
    case Paused = 'paused';

    public function label(): string {
        return match ($this) {
            self::Reported => __('Gemeldet'),
            self::Triaged => __('Triagiert'),
            self::Scheduled => __('Eingeplant'),
            self::InProgress => __('In Arbeit'),
            self::Done => __('Gelöst'),
            self::Accepted => __('Abgenommen'),
            self::Closed => __('Geschlossen'),
            self::Rejected => __('Abgelehnt'),
            self::WaitingCustomer => __('Wartet auf Kunde'),
            self::WaitingExternal => __('Wartet auf Dritte'),
            self::Paused => __('Pausiert'),
        };
    }

    public function isTerminal(): bool {
        return match ($this) {
            self::Closed, self::Rejected => true,
            default => false,
        };
    }

    public function isAcknowledged(): bool {
        return ! in_array($this, [self::Reported], true);
    }

    public function isWaiting(): bool {
        return in_array($this, [self::WaitingCustomer, self::WaitingExternal, self::Paused], true);
    }

    public function isResolved(): bool {
        return in_array($this, [self::Done, self::Accepted, self::Closed, self::Rejected], true);
    }

    /**
     * Warten nur aus triaged/in_progress und zurück nur nach in_progress
     * (Feature 065); done→in_progress ist die Wiederöffnung, accepted/closed
     * öffnen ebenfalls nur nach in_progress (Pflichtgrund im Service).
     *
     * @return list<self>
     */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Reported => [self::Triaged, self::Scheduled, self::InProgress, self::Rejected],
            self::Triaged => [self::Scheduled, self::InProgress, self::Rejected, self::WaitingCustomer, self::WaitingExternal, self::Paused],
            self::Scheduled => [self::InProgress, self::Triaged, self::Rejected],
            self::InProgress => [self::Done, self::Scheduled, self::Rejected, self::WaitingCustomer, self::WaitingExternal, self::Paused],
            self::WaitingCustomer, self::WaitingExternal, self::Paused => [self::InProgress],
            self::Done => [self::Accepted, self::InProgress, self::Closed],
            self::Accepted => [self::Closed, self::InProgress],
            self::Closed => [self::InProgress],
            self::Rejected => [self::Reported],
        };
    }

    /**
     * Spaltenfolge des Queue-Boards (Feature 065, MVP-160) entlang des
     * Lebenszyklus, nicht der Deklarationsreihenfolge.
     *
     * @return list<self>
     */
    public static function boardOrder(): array {
        return [
            self::Reported, self::Triaged, self::Scheduled, self::InProgress,
            self::WaitingCustomer, self::WaitingExternal, self::Paused,
            self::Done, self::Accepted, self::Closed, self::Rejected,
        ];
    }
}
