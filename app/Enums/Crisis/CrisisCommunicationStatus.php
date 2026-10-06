<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisCommunicationStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Crisis;

use App\Enums\Concerns\HasTransitions;
use App\Enums\Contracts\HasStatusTransitions;

/** Stand einer Krisenkommunikation (MVP-217): Entwurf, Freigabe und Aussendung bleiben getrennte Schritte. */
enum CrisisCommunicationStatus: string implements HasStatusTransitions {
    use HasTransitions;

    case Draft = 'draft';
    case Approved = 'approved';
    case Sent = 'sent';

    public function label(): string {
        return (string) __('values.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Sent => 'success',
            self::Approved => 'info',
            self::Draft => 'ghost',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft => [self::Approved],
            self::Approved => [self::Sent],
            self::Sent => [],
        };
    }
}
