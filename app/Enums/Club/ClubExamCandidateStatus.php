<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubExamCandidateStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasStatusTransitions;

/**
 * Zustand eines Prüfungskandidaten (MVP-847): angefragt → zugelassen →
 * Ergebnis (bestanden/nicht bestanden/nicht angetreten); Ablehnung und
 * Rücktritt als Enden. Zulassung und Platzstatus sind getrennt.
 */
enum ClubExamCandidateStatus: string implements HasStatusTransitions {
    use HasOptions;

    case Requested = 'requested';
    case Admitted = 'admitted';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case Passed = 'passed';
    case Failed = 'failed';
    case NoShow = 'no_show';

    public function label(): string {
        return (string) __('enums.club.exam-candidate-status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Requested => 'warning',
            self::Admitted => 'info',
            self::Passed => 'success',
            self::Failed, self::Rejected => 'error',
            self::NoShow, self::Withdrawn => 'ghost',
        };
    }

    public function isResult(): bool {
        return in_array($this, [self::Passed, self::Failed, self::NoShow], true);
    }

    public function isOpen(): bool {
        return $this === self::Requested || $this === self::Admitted;
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Requested => [self::Admitted, self::Rejected, self::Withdrawn],
            self::Admitted => [self::Passed, self::Failed, self::NoShow, self::Rejected, self::Withdrawn, self::Requested],
            self::Rejected, self::Withdrawn => [self::Requested],
            self::Passed, self::Failed, self::NoShow => [],
        };
    }
}
