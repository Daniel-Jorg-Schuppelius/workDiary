<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChangeStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\ServiceTicket;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Lebenszyklus eines Changes (Feature 065, MVP-157): Freigabe, Umsetzung, Abschluss. */
enum ChangeStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    /** Spaltenvorgabe; der Dienst legt Changes gleich „genehmigt“ oder „wartet auf Freigabe“ an. */
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Implementing = 'implementing';
    case Done = 'done';
    case Cancelled = 'cancelled';

    /** Noch nicht abgeschlossen: Assets lassen sich verknüpfen. */
    public function isOpen(): bool {
        return ! in_array($this, [self::Done, self::Cancelled], true);
    }

    public function label(): string {
        return match ($this) {
            self::Draft => (string) __('enums.service_ticket.change_status.draft'),
            self::PendingApproval => (string) __('enums.service_ticket.change_status.pending_approval'),
            self::Approved => (string) __('enums.service_ticket.change_status.approved'),
            self::Implementing => (string) __('enums.service_ticket.change_status.implementing'),
            self::Done => (string) __('enums.service_ticket.change_status.done'),
            self::Cancelled => (string) __('enums.service_ticket.change_status.cancelled'),
        };
    }

    /**
     * Geprüft werden Umsetzung und Abschluss; die Freigabeentscheidung
     * schreibt ohne Blick auf den Stand.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array {
        return match ($this) {
            self::PendingApproval => [self::Approved, self::Cancelled],
            self::Approved => [self::Implementing, self::Done],
            self::Implementing => [self::Done],
            self::Draft, self::Done, self::Cancelled => [],
        };
    }
}
