<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SalesTaxCase.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Invoicing;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Steuerfall eines Ausgangsbelegs, wie ihn ein Faktura- oder
 * Buchhaltungsprogramm als Steuerart braucht (MVP-1712). Die Kategorie nach
 * EN 16931 trennt § 13b im Inland nicht von der Leistung an EU-Unternehmer
 * (beide `AE`) und Kleinunternehmer nicht von § 4 (beide `E`).
 */
enum SalesTaxCase: string implements HasLabel {
    use HasOptions;

    case Standard = 'standard';
    /** § 13b Abs. 2 und 5 UStG im Inland (Kz 60). */
    case DomesticReverseCharge = 'domestic_reverse_charge';
    /** Sonstige Leistung an EU-Unternehmer, § 3a Abs. 2 und § 18b UStG (Kz 21). */
    case EuReverseCharge = 'eu_reverse_charge';
    /** § 4 Nr. 1 Buchstabe b i. V. m. § 6a UStG (Kz 41). */
    case IntraCommunitySupply = 'intra_community_supply';
    /** Ausfuhrlieferung, § 4 Nr. 1 Buchstabe a i. V. m. § 6 UStG (Kz 43). */
    case Export = 'export';
    /** Nicht im Inland steuerbar (Kz 45): Drittland, Grundstück im Ausland, Katalogleistung an Verbraucher im Drittland. */
    case NotTaxable = 'not_taxable';
    case SmallBusiness = 'small_business';
    /** Steuerfrei nach § 4 UStG ohne die Fälle oben. */
    case Exempt = 'exempt';

    public function label(): string {
        return (string) __('enums.invoicing.sales_tax_case.' . $this->value);
    }

    /** Kategorie nach EN 16931. */
    public function category(): string {
        return match ($this) {
            self::Standard => 'S',
            self::DomesticReverseCharge, self::EuReverseCharge => 'AE',
            self::IntraCommunitySupply => 'K',
            self::Export => 'G',
            self::NotTaxable => 'O',
            self::SmallBusiness, self::Exempt => 'E',
        };
    }

    public function isTaxable(): bool {
        return $this === self::Standard;
    }
}
