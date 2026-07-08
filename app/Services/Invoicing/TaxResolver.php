<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaxResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Models\{Customer, Organization};
use CommonToolkit\Helper\Data\Validator;

/**
 * Länderspezifische Steuerlogik der LOKALEN Fakturierung (Restpunkt 68):
 *  - Inland: Org-Override (settings.invoicing.default_tax_rate), sonst
 *    Standardsatz des Verkäuferlandes aus config/taxation.php.
 *  - EU-Ausland B2B mit formal gültiger USt-IdNr. (common-toolkit-Prüfung):
 *    0 % + Reverse-Charge-Kennzeichnung (Pflichthinweis).
 *  - Drittland: 0 % + Leistungsort-Hinweis (kein Reverse Charge).
 * Keine Kursumrechnung — Beträge bleiben in der Belegwährung des Kunden.
 */
class TaxResolver {
    /**
     * @return array{rate: string, reverse_charge: bool, note: ?string}
     */
    public function resolve(Organization $organization, Customer $customer): array {
        // Kleinunternehmer § 19 UStG (Feature 066, MVP-163): kein
        // Steuerausweis — hat Vorrang vor Länderkatalog/Reverse Charge.
        $smallBusiness = (string) data_get((array) ($organization->settings ?? []), 'einvoice.small_business', '0');
        if ($smallBusiness === '1') {
            return [
                'rate' => '0.00',
                'reverse_charge' => false,
                'note' => (string) __('Keine Umsatzsteuer gemäß § 19 UStG (Kleinunternehmerregelung).'),
            ];
        }

        $sellerCountry = $this->sellerCountry($organization);
        $buyerCountry = strtoupper(trim((string) ($customer->country ?? ''))) ?: $sellerCountry;

        // Inland: expliziter Org-Override vor Länderkatalog (bewusst NICHT
        // Setting::get — das fiele auf den config-Default 19.00 zurück und
        // würde den Länderkatalog aushebeln).
        if ($buyerCountry === $sellerCountry) {
            $override = trim((string) data_get((array) ($organization->settings ?? []), 'invoicing.default_tax_rate', ''));

            return [
                'rate' => $override !== '' ? $override : $this->standardRate($sellerCountry),
                'reverse_charge' => false,
                'note' => null,
            ];
        }

        $eu = array_map('strtoupper', (array) config('taxation.eu_countries', []));
        $vatId = str_replace(' ', '', (string) ($customer->vat_id ?? ''));

        // EU-B2B mit formal gültiger USt-IdNr. → Reverse Charge.
        if (in_array($buyerCountry, $eu, true) && $vatId !== '' && Validator::isValidVatId($vatId)) {
            return [
                'rate' => '0.00',
                'reverse_charge' => true,
                'note' => (string) config('taxation.notes.reverse_charge'),
            ];
        }

        // EU-B2C (keine/ungültige USt-ID): Verkäuferland-Satz (MVP, kein OSS).
        if (in_array($buyerCountry, $eu, true)) {
            return [
                'rate' => $this->standardRate($sellerCountry),
                'reverse_charge' => false,
                'note' => null,
            ];
        }

        // Drittland: nicht im Inland steuerbar.
        return [
            'rate' => '0.00',
            'reverse_charge' => false,
            'note' => (string) config('taxation.notes.export'),
        ];
    }

    public function sellerCountry(Organization $organization): string {
        $settings = (array) ($organization->settings ?? []);
        $country = strtoupper(trim((string) data_get($settings, 'einvoice.country', '')));

        return $country !== '' ? $country : 'DE';
    }

    private function standardRate(string $country): string {
        return (string) config("taxation.rates.{$country}.standard", config('taxation.rates.DE.standard', '19.00'));
    }
}
