<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EbicsConnectionStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Finance;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/**
 * Einrichtungsfolge eines EBICS-Zugangs (MVP-124): Zugangsdaten → Schlüssel →
 * INI/HIA gesendet (Brief an die Bank) → Bankschlüssel abgerufen, freigeschaltet.
 */
enum EbicsConnectionStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Draft = 'draft';
    case KeysCreated = 'keys_created';
    case Initialized = 'initialized';
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string {
        return (string) __('enums.finance.ebics-connection-status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Draft, self::KeysCreated => 'ghost',
            self::Initialized => 'info',
            self::Active => 'success',
            self::Suspended => 'warning',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft => [self::KeysCreated],
            self::KeysCreated => [self::Initialized],
            self::Initialized => [self::Active],
            self::Active => [self::Suspended],
            // Nach dem Sperren beginnt die Einrichtung mit neuen Schlüsseln.
            self::Suspended => [self::KeysCreated],
        };
    }
}
