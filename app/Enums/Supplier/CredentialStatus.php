<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CredentialStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Supplier;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Ampel der Pflichtnachweise eines Lieferanten (Feature 117, MVP-606).
 *
 * `Missing` und `Expired` sind bewusst dieselbe Stufe (rot): Für die Haftung
 * macht es keinen Unterschied, ob der Nachweis nie da war oder abgelaufen ist.
 * Unterschieden wird nur in der Meldung, nicht in der Wirkung.
 */
enum CredentialStatus: string implements HasLabel {
    use HasOptions;

    case Ok = 'ok';
    case Expiring = 'expiring';
    case Missing = 'missing';
    case Expired = 'expired';

    public function label(): string {
        return (string) __('enums.credential_status.' . $this->value);
    }

    public function isBlocking(): bool {
        return $this === self::Missing || $this === self::Expired;
    }

    public function tone(): string {
        return match ($this) {
            self::Ok => 'success',
            self::Expiring => 'warning',
            self::Missing, self::Expired => 'error',
        };
    }
}
