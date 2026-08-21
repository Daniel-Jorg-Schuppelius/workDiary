<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WarrantyStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Warranty;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Gewährleistungsfristen (Feature 115, MVP-604). */
enum WarrantyStatus: string implements HasLabel {
    use HasOptions;

    case Open = 'open';
    case Closed = 'closed';
    case Claimed = 'claimed';

    public function label(): string {
        return (string) __('enums.warranty_status.' . $this->value);
    }
}
