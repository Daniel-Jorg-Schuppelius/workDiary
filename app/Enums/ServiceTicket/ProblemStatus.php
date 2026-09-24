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
            self::Open => (string) __('Offen'),
            self::Analyzing => (string) __('In Analyse'),
            self::KnownError => (string) __('Known Error'),
            self::Resolved => (string) __('Gelöst'),
            self::Closed => (string) __('Geschlossen'),
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
