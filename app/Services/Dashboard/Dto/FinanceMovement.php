<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FinanceMovement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Dashboard\Dto;

use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Money;

/** Eine Finanzbewegung der Kachel „Finanzbewegungen“; Eingänge positiv, Ausgänge negativ. */
final readonly class FinanceMovement {
    public function __construct(
        public CarbonImmutable $bookedOn,
        public string $title,
        public Money $amount,
        public ?string $detail = null,
        public ?bool $assigned = null,
    ) {}
}
