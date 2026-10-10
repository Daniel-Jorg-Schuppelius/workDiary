<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProblemStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\ServiceTicket;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/** Lebenszyklus eines Problems (Problem-Management, MVP-156). */
enum ProblemStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Open = 'open';
    case Analyzing = 'analyzing';
    case KnownError = 'known_error';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string {
        return match ($this) {
            self::Open => (string) __('enums.service_ticket.problem_status.open'),
            self::Analyzing => (string) __('enums.service_ticket.problem_status.analyzing'),
            self::KnownError => (string) __('enums.service_ticket.problem_status.known_error'),
            self::Resolved => (string) __('enums.service_ticket.problem_status.resolved'),
            self::Closed => (string) __('enums.service_ticket.problem_status.closed'),
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Open => [self::Analyzing, self::Closed],
            self::Analyzing => [self::KnownError, self::Resolved, self::Open],
            self::KnownError => [self::Resolved],
            self::Resolved => [self::Closed],
            self::Closed => [],
        };
    }
}
