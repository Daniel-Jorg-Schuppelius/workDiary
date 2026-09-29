<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalCategorySpend.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Dto;

use Carbon\CarbonImmutable;

/** Ausgaben eines Belegs je Buchungskategorie (MVP-1036), netto mit Vorzeichen. */
final readonly class ExternalCategorySpend {
    public function __construct(
        public string $category,
        public CarbonImmutable $date,
        public float $amount,
    ) {}
}
