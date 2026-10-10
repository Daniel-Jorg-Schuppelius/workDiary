<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeCorrectionStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\TimeApproval;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/**
 * Status eines Zeit-Korrekturantrags (MVP-017, ../WorkDiary-Architecture/zeit-korrekturen.md §4).
 */
enum TimeCorrectionStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Applied = 'applied';
    case Withdrawn = 'withdrawn';

    public function label(): string {
        return match ($this) {
            self::Draft     => __('enums.time_approval.time_correction_status.draft'),
            self::Submitted => __('enums.time_approval.time_correction_status.submitted'),
            self::Approved  => __('enums.time_approval.time_correction_status.approved'),
            self::Rejected  => __('enums.time_approval.time_correction_status.rejected'),
            self::Applied   => __('enums.time_approval.time_correction_status.applied'),
            self::Withdrawn => __('enums.time_approval.time_correction_status.withdrawn'),
        };
    }

    public function tone(): string {
        return match ($this) {
            self::Draft     => 'ghost',
            self::Submitted => 'info',
            self::Approved  => 'success',
            self::Rejected  => 'error',
            self::Applied   => 'success',
            self::Withdrawn => 'ghost',
        };
    }

    public function isTerminal(): bool {
        return in_array($this, [self::Applied, self::Rejected, self::Withdrawn], true);
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft => [self::Submitted, self::Withdrawn],
            self::Submitted => [self::Approved, self::Rejected, self::Withdrawn],
            self::Approved => [self::Applied],
            self::Rejected, self::Applied, self::Withdrawn => [],
        };
    }
}
