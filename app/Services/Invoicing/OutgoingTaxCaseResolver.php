<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OutgoingTaxCaseResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Enums\Invoicing\{InvoiceStatus, PlaceOfSupplyKind, SalesTaxCase};
use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\Organization;
use App\Services\Invoicing\Dto\OutgoingTaxCase;
use App\Services\Invoicing\EInvoice\XRechnungGenerator;
use CommonToolkit\Helper\Data\StringHelper;

/**
 * Steuerfall eines Ausgangsbelegs für Faktura- und Buchhaltungsprogramme
 * (MVP-1712). Die Kategorie kommt aus dem {@see TaxResolver} bzw. dem beim
 * Ausstellen eingefrorenen Steuerkontext; verfeinert wird nur, was die
 * Kategorie offen lässt — wie die Buchhaltung
 * (`SalesInvoiceAdapter::zeroRatedCategory()`): `AE` mit Empfänger im Inland
 * ist § 13b, bei Waren an EU-Unternehmer eine innergemeinschaftliche
 * Lieferung; `G` an Leistungen (Belege vor MVP-1684) ist nicht steuerbar;
 * `E` ist § 19 nur beim Kleinunternehmer.
 */
final class OutgoingTaxCaseResolver {
    public function __construct(
        private readonly TaxResolver $taxes,
        private readonly PlaceOfSupplyResolver $places,
    ) {}

    /**
     * Leistungen ohne Rechnung (Übergabe aus Zeiten, Material, Abos): je
     * Leistungsort der Fall, den der Rechnungslauf einfröre, ohne Doppel.
     * Mehr als einer heißt: zu teilen.
     *
     * @param  list<PlaceOfSupply>  $places  leer = Vorgabe der Organisation
     * @return list<OutgoingTaxCase>
     */
    public function forPlaces(Organization $organization, Customer $customer, ?\DateTimeInterface $onDate, bool $goods, array $places): array {
        $buyer = ['country' => (string) $customer->country, 'vat_id' => (string) $customer->vat_id, 'is_consumer' => $customer->is_consumer ? '1' : '0'];
        $cases = [];
        foreach ($places !== [] ? $places : [null] as $place) {
            $tax = $this->taxes->resolve($organization, $customer, $onDate, $goods ? 'goods' : 'services', $place);
            // Der TaxResolver vergibt E nur dem Kleinunternehmer.
            $cases[] = $this->refine($organization, $customer, $buyer, $tax['category'], $goods, $place !== null ? [$place] : [], $tax['note'], $tax['category'] === 'E');
        }

        return self::unique($cases);
    }

    /**
     * Steuerfall einer Rechnung: ausgestellt der eingefrorene, als Entwurf der,
     * den das Ausstellen einfrieren wird — nach Kunde und Leistungsort der
     * Positionen, bei Storno, Gutschrift und Import der des Originals bzw.
     * nach Kunde.
     *
     * @return list<OutgoingTaxCase>
     */
    public function forInvoice(Invoice $invoice): array {
        $organization = $invoice->organization ?? throw new \LogicException('Steuerfall ohne Organisation.');
        $invoice->loadMissing(['items', 'customer']);
        $goods = $invoice->isMaterial();
        $buyer = $invoice->recipient();
        $places = $goods ? [] : $this->places->forInvoice($invoice);
        $smallBusiness = (bool) app(XRechnungGenerator::class)->sellerData($invoice)['small_business'];

        $frozen = $this->frozenCategory($invoice, $smallBusiness);
        if ($frozen !== null) {
            return [$this->refine($organization, $invoice->customer, $buyer, $frozen, $goods, $places, $this->noteInText($invoice, $organization, $frozen), $smallBusiness)];
        }

        $cases = [];
        foreach ($this->taxes->casesToFreeze($invoice) as $tax) {
            // Storno und Gutschrift übernehmen den Hinweis des Originals im Text.
            $cases[] = $this->refine($organization, $invoice->customer, $buyer, $tax['category'], $goods, $places, $tax['note'] ?? $this->noteInText($invoice, $organization, $tax['category']), $smallBusiness);
        }

        return self::unique($cases);
    }

    /** Beim Ausstellen eingefrorene Kategorie; null = Entwurf, der Fall kommt aus {@see TaxResolver::casesToFreeze()}. */
    private function frozenCategory(Invoice $invoice, bool $smallBusiness): ?string {
        $own = trim((string) data_get($invoice->tax_context, 'category', ''));
        if ($own !== '') {
            return $own;
        }
        // Ausgestellt vor dem Einfrieren der Kategorie: wie die Buchhaltung über das Reverse-Charge-Merkmal.
        if ($invoice->status !== InvoiceStatus::Draft) {
            return $invoice->is_reverse_charge ? 'AE' : ($smallBusiness ? 'E' : 'S');
        }

        return null;
    }

    /**
     * @param  array<string, string>  $buyer  Empfänger wie auf dem Beleg (Land, USt-IdNr., Verbraucher)
     * @param  list<PlaceOfSupply>  $places
     */
    private function refine(Organization $organization, ?Customer $customer, array $buyer, string $category, bool $goods, array $places, ?string $note, bool $smallBusiness): OutgoingTaxCase {
        $seller = $this->taxes->sellerCountry($organization);
        $buyerCountry = strtoupper(trim($buyer['country'] ?? '')) ?: $seller;

        $case = match (strtoupper(trim($category))) {
            'AE' => match (true) {
                $this->domesticReverseCharge($customer, $buyer, $buyerCountry, $seller, $places) => SalesTaxCase::DomesticReverseCharge,
                $goods => SalesTaxCase::IntraCommunitySupply,
                default => SalesTaxCase::EuReverseCharge,
            },
            'K' => SalesTaxCase::IntraCommunitySupply,
            'G' => $goods ? SalesTaxCase::Export : SalesTaxCase::NotTaxable,
            'O' => SalesTaxCase::NotTaxable,
            'E' => $smallBusiness ? SalesTaxCase::SmallBusiness : SalesTaxCase::Exempt,
            // S, Z (Nullsatz), L, M: steuerpflichtig, der Satz steht an der Position.
            default => SalesTaxCase::Standard,
        };

        $countries = [$buyerCountry];
        if ($case === SalesTaxCase::NotTaxable && $places !== []) {
            // Grundstück im Ausland: es zählt das Land des Grundstücks, nicht der Sitz des Empfängers.
            $countries = array_map(static fn (PlaceOfSupply $place): string => $place->kind === PlaceOfSupplyKind::Property && $place->propertyCountry !== null && $place->propertyCountry !== $seller
                ? $place->propertyCountry
                : $buyerCountry, $places);
        }
        $regions = array_values(array_unique(array_map(fn (string $country): string => $this->region($country, $seller), $countries)));

        return new OutgoingTaxCase($case, StringHelper::trimToNull($note), count($regions) === 1 ? $regions[0] : null, trim($buyer['vat_id'] ?? '') !== '');
    }

    /**
     * § 13b im Inland wie im {@see TaxResolver}: Empfänger im Inland, oder ein
     * Unternehmer mit § 13b-Merkmal bei einem Grundstück im Inland.
     *
     * @param  array<string, string>  $buyer
     * @param  list<PlaceOfSupply>  $places
     */
    private function domesticReverseCharge(?Customer $customer, array $buyer, string $buyerCountry, string $seller, array $places): bool {
        if ($buyerCountry === $seller) {
            return true;
        }
        if (! (bool) $customer?->is_reverse_charge_recipient || ($buyer['is_consumer'] ?? '0') === '1') {
            return false;
        }
        foreach ($places as $place) {
            if ($place->kind === PlaceOfSupplyKind::Property && ($place->propertyCountry ?? $seller) === $seller) {
                return true;
            }
        }

        return false;
    }

    private function region(string $country, string $seller): string {
        if ($country === $seller) {
            return OutgoingTaxCase::REGION_DOMESTIC;
        }

        return in_array($country, array_map('strtoupper', (array) config('taxation.eu_countries', [])), true)
            ? OutgoingTaxCase::REGION_EU
            : OutgoingTaxCase::REGION_THIRD_COUNTRY;
    }

    /** Automatischer Hinweis der Kategorie, der im Rechnungstext steht. */
    private function noteInText(Invoice $invoice, Organization $organization, string $category): ?string {
        $text = (string) $invoice->notes;
        foreach (app(TaxExemptionNotes::class)->automatic($organization, $category) as $note) {
            if (str_contains($text, $note)) {
                return $note;
            }
        }

        return null;
    }

    /**
     * Ein Eintrag je Fall; liegen die Leistungsorte eines Falls in mehreren Gebieten, bleibt das Gebiet offen.
     *
     * @param  list<OutgoingTaxCase>  $cases
     * @return list<OutgoingTaxCase>
     */
    private static function unique(array $cases): array {
        $unique = [];
        foreach ($cases as $case) {
            $known = $unique[$case->case->value] ?? null;
            $unique[$case->case->value] = $known === null || $known->region === $case->region
                ? ($known ?? $case)
                : new OutgoingTaxCase($known->case, $known->note, null, $known->buyerHasVatId);
        }

        return array_values($unique);
    }
}
