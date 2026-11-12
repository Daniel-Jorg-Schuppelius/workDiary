<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GuaranteeStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Guarantee;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Bürgschaftsregister (Feature 114, MVP-603). */
enum GuaranteeStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Active = 'active';
    case Returned = 'returned';
    case Drawn = 'drawn';
    case Expired = 'expired';

    /**
     * Verfallen ist nicht endgültig: Die Urkunde kann noch zurückkommen, und
     * eine nachgetragene Verlängerung macht die Bürgschaft wieder aktiv.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Active => [self::Returned, self::Drawn, self::Expired],
            self::Expired => [self::Returned, self::Active],
            self::Returned, self::Drawn => [],
        };
    }

    public function label(): string {
        return (string) __('enums.guarantee_status.' . $this->value);
    }

    /** Läuft die Bürgschaft noch — also gilt die Sicherheit? */
    public function isActive(): bool {
        return $this === self::Active;
    }

    public function tone(): string {
        return match ($this) {
            self::Active => 'success',
            self::Returned => 'neutral',
            self::Drawn => 'error',
            self::Expired => 'warning',
        };
    }
}
