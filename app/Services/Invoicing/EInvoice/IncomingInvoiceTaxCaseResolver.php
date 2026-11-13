<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceTaxCaseResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\EInvoice;

use App\Enums\Invoicing\IncomingInvoiceTaxCase;
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\Organization;
use App\Models\Supplier\Supplier;
use App\Services\Invoicing\TaxResolver;
use CommonToolkit\Enums\CountryCode;
use CommonToolkit\Helper\Data\VatNumberHelper;

/**
 * Steuerfall einer Eingangsrechnung (MVP-1621).
 *
 * Reihenfolge: von Hand gesetzt; Steuerkategorie der E-Rechnung (UNTDID 5305:
 * `K` innergemeinschaftlicher Erwerb, `AE` Steuerschuld des Empfängers);
 * Vorgabe des Lieferanten, solange der Beleg keine Steuer ausweist; sonst
 * Vorsteuer aus der Rechnung. Bei `AE` eines Lieferanten aus dem übrigen
 * Gemeinschaftsgebiet gilt die sonstige Leistung (§ 13b Abs. 1 UStG), es sei
 * denn, der Lieferant ist als § 13b Abs. 2 geführt (etwa Werklieferungen).
 */
class IncomingInvoiceTaxCaseResolver {
    public const string SOURCE_MANUAL = 'manual';

    public const string SOURCE_DOCUMENT = 'document';

    public const string SOURCE_SUPPLIER = 'supplier';

    public const string SOURCE_DEFAULT = 'default';

    public function __construct(private readonly TaxResolver $taxes) {}

    /**
     * @return array{case: IncomingInvoiceTaxCase, source: string}
     */
    public function resolve(IncomingEInvoice $incoming): array {
        if ($incoming->tax_case instanceof IncomingInvoiceTaxCase) {
            return ['case' => $incoming->tax_case, 'source' => self::SOURCE_MANUAL];
        }

        $supplier = $incoming->supplier_id !== null ? Supplier::query()->withoutGlobalScopes()->find($incoming->supplier_id) : null;
        $preset = $supplier?->incoming_tax_case;

        $fromDocument = $this->fromDocument($incoming, $supplier, $preset);
        if ($fromDocument !== null) {
            return ['case' => $fromDocument, 'source' => self::SOURCE_DOCUMENT];
        }

        $untaxed = $incoming->amount_tax === null || $incoming->amount_tax->isZero();
        if ($preset instanceof IncomingInvoiceTaxCase && $untaxed) {
            return ['case' => $preset, 'source' => self::SOURCE_SUPPLIER];
        }

        return ['case' => IncomingInvoiceTaxCase::Regular, 'source' => self::SOURCE_DEFAULT];
    }

    private function fromDocument(IncomingEInvoice $incoming, ?Supplier $supplier, ?IncomingInvoiceTaxCase $preset): ?IncomingInvoiceTaxCase {
        $categories = array_map(
            static fn (array $row): string => strtoupper(trim((string) ($row['category'] ?? ''))),
            $incoming->taxBreakdown(),
        );
        if (in_array('K', $categories, true)) {
            return IncomingInvoiceTaxCase::IntraCommunityAcquisition;
        }
        if (! in_array('AE', $categories, true)) {
            return null;
        }

        return $this->sellerInOtherEuCountry($incoming, $supplier) && $preset !== IncomingInvoiceTaxCase::ReverseCharge
            ? IncomingInvoiceTaxCase::ReverseChargeEu
            : IncomingInvoiceTaxCase::ReverseCharge;
    }

    private function sellerInOtherEuCountry(IncomingEInvoice $incoming, ?Supplier $supplier): bool {
        $country = $this->sellerCountry($incoming, $supplier);
        $organization = Organization::query()->find($incoming->organization_id);
        $own = $organization instanceof Organization ? $this->taxes->sellerCountry($organization) : 'DE';

        return $country instanceof CountryCode && $country->value !== $own && $country->isEU();
    }

    /** Land des Lieferanten: aus der E-Rechnung, dem Stammsatz, sonst aus dem Präfix der USt-IdNr. */
    private function sellerCountry(IncomingEInvoice $incoming, ?Supplier $supplier): ?CountryCode {
        $candidates = [
            (string) data_get($incoming->summary, 'seller_country', ''),
            (string) ($supplier->country ?? ''),
        ];
        foreach ($candidates as $candidate) {
            $country = CountryCode::tryFrom(strtoupper(trim($candidate)));
            if ($country !== null) {
                return $country;
            }
        }

        $vatId = trim((string) ($incoming->seller_vat_id ?? $supplier->vat_id ?? ''));

        return $vatId !== ''
            ? VatNumberHelper::getCountryForVatPrefix((string) VatNumberHelper::extractCountryCode(VatNumberHelper::normalize($vatId)))
            : null;
    }
}
