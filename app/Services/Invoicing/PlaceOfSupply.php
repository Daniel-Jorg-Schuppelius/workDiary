<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlaceOfSupply.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Enums\Invoicing\PlaceOfSupplyKind;

/**
 * Leistungsort einer Leistung (MVP-1688): Art und, bei einer
 * Grundstücksleistung, das Land des Grundstücks. Ohne Land liegt das
 * Grundstück im Inland.
 */
final readonly class PlaceOfSupply {
    public ?string $propertyCountry;

    public function __construct(public PlaceOfSupplyKind $kind, ?string $propertyCountry = null) {
        $country = strtoupper(trim((string) $propertyCountry));
        $this->propertyCountry = $kind === PlaceOfSupplyKind::Property && $country !== '' ? $country : null;
    }

    public function key(): string {
        return $this->kind->value . ':' . ($this->propertyCountry ?? '');
    }
}
