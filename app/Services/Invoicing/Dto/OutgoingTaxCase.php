<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OutgoingTaxCase.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Dto;

use App\Enums\Invoicing\SalesTaxCase;

/**
 * Steuerfall eines Ausgangsbelegs für die Übergabe an ein Faktura- oder
 * Buchhaltungsprogramm (MVP-1712): Fall, Pflichthinweis und das Gebiet, in
 * dem die Leistung ausgeführt wird. Das Gebiet unterscheidet bei „nicht
 * steuerbar“ Drittland und EU (Grundstück im Ausland); null heißt, die
 * Leistungsorte liegen in mehreren Gebieten.
 */
final readonly class OutgoingTaxCase {
    public const string REGION_DOMESTIC = 'domestic';

    public const string REGION_EU = 'eu';

    public const string REGION_THIRD_COUNTRY = 'third_country';

    public function __construct(
        public SalesTaxCase $case,
        public ?string $note = null,
        public ?string $region = self::REGION_DOMESTIC,
        public bool $buyerHasVatId = false,
    ) {}

    /** Positionen dieses Falls tragen 0 %. */
    public function zeroRated(): bool {
        return ! $this->case->isTaxable();
    }

    /** Hinweistext, sonst die Bezeichnung des Falls. */
    public function noteOrLabel(): string {
        $note = trim((string) $this->note);

        return $note !== '' ? $note : $this->case->label();
    }
}
