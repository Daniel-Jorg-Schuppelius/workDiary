<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServicePlanCellState.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\ServicePlan;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;
use App\Enums\Diary\Status;

/**
 * Stand einer Zelle im Leistungsplan (Feature 199, MVP-1638/1639). Kein
 * eigener Lebenszyklus: Mit Auftrag folgt der Stand dessen Status
 * ({@see fromOrderStatus()}), ohne Auftrag entscheidet der Leistungsumfang —
 * Pflicht „fehlt“, Wahl bleibt still, „entfällt“ ist von Hand markiert.
 * `None` heißt: die Leistung gilt in dieser Periode nicht (außerhalb der
 * Gültigkeit), die Zelle bleibt leer.
 */
enum ServicePlanCellState: string implements HasLabel {
    use HasOptions;

    case Missing = 'missing';
    case Optional = 'optional';
    case Planned = 'planned';
    case Accepted = 'accepted';
    case InProgress = 'in_progress';
    case Waiting = 'waiting';
    case Review = 'review';
    case Done = 'done';
    case Waived = 'waived';
    case None = 'none';

    /** Filter und Zählkacheln: Gruppen über den Zuständen (`overdue` rechnet die Fälligkeit). */
    public const array FILTERS = ['missing', 'open', 'review', 'done', 'waived', 'overdue'];

    public function label(): string {
        return (string) __('enums.service_plan.service_plan_cell_state.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Missing => 'error',
            self::Planned, self::Accepted => 'neutral',
            self::InProgress => 'info',
            self::Waiting => 'warning',
            self::Review => 'accent',
            self::Done => 'success',
            self::Optional, self::Waived, self::None => 'ghost',
        };
    }

    /** Stand eines (nicht stornierten) Auftrags. */
    public static function fromOrderStatus(Status $status): self {
        return match ($status) {
            Status::Open => self::Planned,
            Status::Accepted => self::Accepted,
            Status::InProgress => self::InProgress,
            Status::Problem, Status::WaitingMaterial => self::Waiting,
            Status::InReview, Status::ReviewQueried, Status::ReviewApproved => self::Review,
            Status::Done, Status::AcceptedFinal, Status::Invoiced => self::Done,
            // Stornierte Aufträge liest der Plan nicht; zur Sicherheit wie „kein Auftrag“.
            Status::Cancelled => self::Optional,
        };
    }

    /** Filtergruppe der Zählkacheln (null: zählt nicht). */
    public function filterGroup(): ?string {
        return match ($this) {
            self::Missing => 'missing',
            self::Planned, self::Accepted, self::InProgress, self::Waiting => 'open',
            self::Review => 'review',
            self::Done => 'done',
            self::Waived => 'waived',
            self::Optional, self::None => null,
        };
    }

    /** Noch Arbeit offen (kann überfällig sein)? */
    public function isPending(): bool {
        return in_array($this, [self::Missing, self::Planned, self::Accepted, self::InProgress, self::Waiting, self::Review], true);
    }
}
