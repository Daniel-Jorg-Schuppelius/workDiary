<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProblemReportStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Support;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Status einer Fehlermeldung (Feature 041, MVP-053). Bewusst schlank —
 * Rückfragen-Threads laufen bei aktivem Helpdesk im übernommenen
 * Ticket, nicht hier (kein zweites Konversationsmodell).
 */
enum ProblemReportStatus: string implements HasLabel {
    use HasOptions;

    case New_ = 'new';
    case InReview = 'in_review';
    case Answered = 'answered';
    case Closed = 'closed';

    public function label(): string {
        return __('problemreport.status.' . $this->value);
    }

    public function isOpen(): bool {
        return $this !== self::Closed;
    }

    public function tone(): string {
        return match ($this) {
            self::New_ => 'warning',
            self::InReview => 'info',
            self::Answered => 'success',
            self::Closed => 'neutral',
        };
    }
}
