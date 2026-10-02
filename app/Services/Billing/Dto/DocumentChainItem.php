<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentChainItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Dto;

use Carbon\CarbonInterface;
use CommonToolkit\ValueObjects\Money;

/** Ein offener Schritt der Belegkette (MVP-1057). */
final readonly class DocumentChainItem {
    public function __construct(
        public string $title,
        public ?string $detail,
        public string $url,
        public ?Money $amount = null,
        public ?CarbonInterface $date = null,
    ) {}
}
