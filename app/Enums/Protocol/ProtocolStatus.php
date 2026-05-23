<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Protocol;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum ProtocolStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case InReview = 'in_review';
    case Signed = 'signed';
    case Archived = 'archived';
    case Superseded = 'superseded';

    public function label(): string {
        return (string) __('enums.protocol.status.' . $this->value);
    }

    public function isEditable(): bool {
        return $this === self::Draft;
    }

    public function isImmutable(): bool {
        return in_array($this, [self::Signed, self::Archived, self::Superseded], true);
    }

    /** @return list<string> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft => ['requestReview', 'signDirect', 'archive'],
            self::InReview => ['returnToDraft', 'sign'],
            self::Signed => ['archive', 'supersede'],
            self::Archived, self::Superseded => [],
        };
    }
}
