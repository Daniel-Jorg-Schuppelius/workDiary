<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AdviceStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Procurement;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Status eines Lieferavis (ASN) – Feature 048, E4: angekündigt → vereinnahmt
 * (Wareneingang gebucht) bzw. storniert.
 */
enum AdviceStatus: string implements HasLabel {
    use HasOptions;

    case Announced = 'announced';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string {
        return __('procurement.advice_status.' . $this->value);
    }

    public function isOpen(): bool {
        return $this === self::Announced;
    }
}
