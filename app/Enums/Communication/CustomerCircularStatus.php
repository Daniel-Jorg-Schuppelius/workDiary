<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerCircularStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Communication;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Status eines Kundenrundschreibens (Feature 119, MVP-608). */
enum CustomerCircularStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Draft = 'draft';
    case Sending = 'sending';
    case Sent = 'sent';

    public function label(): string {
        return (string) __('circular.status.' . $this->value);
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft => [self::Sending],
            self::Sending => [self::Sent],
            self::Sent => [],
        };
    }
}
