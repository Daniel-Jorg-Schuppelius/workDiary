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

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Status eines Zeit-Korrekturantrags (MVP-017, ../WorkDiary-Architecture/zeit-korrekturen.md §4).
 */
enum TimeCorrectionStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Applied = 'applied';
    case Withdrawn = 'withdrawn';

    public function label(): string {
        return match ($this) {
            self::Draft     => __('Entwurf'),
            self::Submitted => __('Eingereicht'),
            self::Approved  => __('Genehmigt'),
            self::Rejected  => __('Abgelehnt'),
            self::Applied   => __('Angewendet'),
            self::Withdrawn => __('Zurückgezogen'),
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
}
