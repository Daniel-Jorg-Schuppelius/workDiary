<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EvidenceReviewStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Contract;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Prüfentscheidung zu einem hochgeladenen Nachweis (Feature 157). Erst die
 * bestätigte Prüfung zählt als Unterzeichnung; Browser-Signaturen tragen
 * keinen Prüfstatus.
 */
enum EvidenceReviewStatus: string implements HasLabel {
    use HasOptions;

    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string {
        return (string) __('contract-signing.review_status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Pending => 'info',
            self::Accepted => 'success',
            self::Rejected => 'error',
        };
    }
}
