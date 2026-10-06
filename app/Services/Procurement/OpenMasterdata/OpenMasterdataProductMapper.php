<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenMasterdataProductMapper.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement\OpenMasterdata;

use CommonToolkit\Helper\Data\NumberHelper;

/**
 * Übersetzt ein Open-Masterdata-Produkt in den Katalogdatensatz des
 * {@see \App\Services\Procurement\CatalogItemUpserter}. Tolerant gegen die
 * Abweichungen der Großhändler: Zahlen kommen als Zahl oder Text, Listen
 * fehlen oder sind leer, Feldnamen weichen in Einzelfällen ab.
 */
class OpenMasterdataProductMapper {
    /** Felder, die ein reiner Preis- und Verfügbarkeitsabgleich nachführt. */
    public const PRICE_FIELDS = ['external_no', 'purchase_price', 'list_price', 'currency', 'price_unit_amount', 'price_type', 'unit', 'lead_time_days', 'availability', 'discount_group', 'pack_size'];

    private const PICTURE_MAIN = 'B_';

    private const DOCUMENT_PREFERENCE = ['DB', 'TI', 'MA'];

    private const LIST_LIMIT = 20;

    /** @return array<string, mixed> */
    public function toRecord(OpenMasterdataProduct $product): array {
        $net = $this->price($product, 'prices.netPrice');
        $list = $this->price($product, 'prices.listPrice');
        $effective = $net ?? $list;
        $expiring = $product->string('additional.expiringProduct');
        $isExpiring = $expiring !== null && in_array(strtolower($expiring), ['true', 'yes', 'yes-successor'], true);

        $record = [
            'external_no' => $product->supplierPid(),
            'name' => $product->string('basic.productShortDescr') ?? $product->string('descriptions.shorttext1') ?? $product->supplierPid(),
            'description' => $product->string('descriptions.productDescr') ?? $product->string('descriptions.marketingText'),
            'category' => $product->string('basic.commodityGroupDescr') ?? $product->string('basic.mainCommodityGroupDescr'),
            'gtin' => $product->string('gtin'),
            'matchcode' => $product->string('basic.matchcode'),
            'manufacturer_no' => $product->string('manufacturerPid'),
            'product_url' => $this->url($product->string('additional.deepLink')),
            'image_url' => $this->mainPicture($product),
            'datasheet_url' => $this->mainDocument($product),
            'purchase_price' => $effective['value'] ?? null,
            'list_price' => $list['value'] ?? null,
            'currency' => $effective['currency'] ?? 'EUR',
            'price_unit_amount' => $effective['basis'] ?? null,
            'price_type' => $net !== null ? 'net' : ($list !== null ? 'list' : ($product->get('basic.priceOnDemand') === true ? 'on_request' : null)),
            'unit' => $effective['unit'] ?? $product->string('additional.minOrderUnit'),
            'pack_size' => NumberHelper::normalizeDecimalStringOrNull($product->string('additional.minOrderQuantity')) ?? '1',
            'base_qty' => '1',
            'discount_group' => $product->string('additional.discoundGroupIdManufacturer') ?? $product->string('additional.discountGroupIdManufacturer'),
            'lead_time_days' => $this->int($product->get('logistics.standardDeliveryPeriod')),
            'availability' => $isExpiring ? 'expiring' : null,
            'extra_attributes' => $this->extraAttributes($product, $expiring),
        ];

        return array_filter($record, static fn ($value): bool => $value !== null && $value !== '');
    }

    /**
     * Teilsatz für den Preisabgleich: lässt Texte, Bilder und Dokumente in Ruhe.
     *
     * @return array<string, mixed>
     */
    public function toPriceRecord(OpenMasterdataProduct $product): array {
        $record = array_intersect_key($this->toRecord($product), array_flip(self::PRICE_FIELDS));
        if (! array_key_exists('availability', $record)) {
            // Delta-Läufe lassen fehlende Felder unverändert; ein wieder lieferbarer Artikel muss das Kennzeichen verlieren.
            $record['availability'] = null;
        }

        return $record;
    }

    /** @return array{value: string, currency: string, basis: int, unit: ?string}|null */
    private function price(OpenMasterdataProduct $product, string $path): ?array {
        $value = NumberHelper::normalizeDecimalStringOrNull($product->string($path . '.value'));
        if ($value === null) {
            return null;
        }
        $basis = $this->int($product->get($path . '.basis'));

        return [
            'value' => $value,
            'currency' => strtoupper($product->string($path . '.currency') ?? 'EUR'),
            'basis' => $basis !== null && $basis > 0 ? $basis : 1,
            'unit' => $product->string($path . '.quantityUnit'),
        ];
    }

    private function mainPicture(OpenMasterdataProduct $product): ?string {
        $pictures = $this->list($product->get('pictures'));
        usort($pictures, static fn (array $a, array $b): int => [($a['type'] ?? '') === self::PICTURE_MAIN ? 0 : 1, (int) ($a['sortOrder'] ?? 0)] <=> [($b['type'] ?? '') === self::PICTURE_MAIN ? 0 : 1, (int) ($b['sortOrder'] ?? 0)]);

        return $this->url($pictures[0]['url'] ?? null);
    }

    private function mainDocument(OpenMasterdataProduct $product): ?string {
        $documents = $this->list($product->get('documents'));
        foreach (self::DOCUMENT_PREFERENCE as $type) {
            foreach ($documents as $document) {
                if (($document['type'] ?? null) === $type && $this->url($document['url'] ?? null) !== null) {
                    return $this->url($document['url']);
                }
            }
        }

        return $this->url($documents[0]['url'] ?? null);
    }

    /** @return array<string, mixed> */
    private function extraAttributes(OpenMasterdataProduct $product, ?string $expiring): array {
        $extra = array_filter([
            'omd_manufacturer_id' => $product->string('manufacturerId'),
            'omd_manufacturer_id_type' => $product->string('manufacturerIdType'),
            'omd_product_type' => $product->string('basic.productType'),
            'omd_series' => $product->string('basic.serie'),
            'omd_model' => $product->string('basic.modelNumber'),
            'omd_rrp' => NumberHelper::normalizeDecimalStringOrNull($product->string('prices.rrp.value') ?? $product->string('basic.rrp.value')),
            'omd_tax_code' => $product->string('prices.taxCode'),
            'omd_commodity_number' => $product->string('logistics.commodityNumber'),
            'omd_country_of_origin' => $product->string('logistics.countryOfOrigin'),
            'omd_hazardous' => $product->get('logistics.hazardousMaterial') === true ? 'true' : null,
            'omd_energy_efficiency_class' => $product->string('additional.energyEfficiencyClass'),
            'omd_expiring' => $expiring,
            'omd_expiring_date' => $product->string('additional.expiringDate'),
            'omd_successor' => $this->firstPid($product->get('additional.followupProduct')),
            'omd_alternative' => $this->firstPid($product->get('additional.alternativeProduct')),
            'omd_fetched_at' => now()->toIso8601String(),
        ], static fn ($value): bool => $value !== null && $value !== '');

        $documents = [];
        foreach (array_slice($this->list($product->get('documents')), 0, self::LIST_LIMIT) as $document) {
            $url = $this->url($document['url'] ?? null);
            if ($url !== null) {
                $documents[] = array_filter(['type' => $document['type'] ?? null, 'url' => $url, 'filename' => $document['filename'] ?? null, 'description' => $document['description'] ?? null]);
            }
        }
        if ($documents !== []) {
            $extra['omd_documents'] = $documents;
        }

        $attributes = [];
        foreach (array_slice($this->list($product->get('additional.attribute') ?? $product->get('additional.attributes')), 0, 100) as $attribute) {
            $name = trim((string) ($attribute['attributeName'] ?? ''));
            // Rohwert plus Einheit; die Beschreibung („18 Volt“) nur, wenn der Rohwert fehlt.
            $raw = trim((string) ($attribute['attributeValue1'] ?? ''));
            $value = $raw !== '' ? $raw : trim((string) ($attribute['attributeValue1Descr'] ?? $attribute['attributeValue1Desc'] ?? ''));
            if ($name === '' || $value === '') {
                continue;
            }
            $unit = trim((string) ($attribute['attributeUnit'] ?? ''));
            $attributes[$name] = $raw !== '' && $unit !== '' ? $value . ' ' . $unit : $value;
        }
        if ($attributes !== []) {
            $extra['etim'] = $attributes;
        }

        return $extra;
    }

    private function firstPid(mixed $linked): ?string {
        $first = $this->list($linked)[0] ?? null;

        return is_array($first) && isset($first['supplierPid']) ? trim((string) $first['supplierPid']) : null;
    }

    /** @return list<array<string, mixed>> */
    private function list(mixed $value): array {
        if (! is_array($value)) {
            return [];
        }
        // Einzelobjekt statt Liste kommt bei manchen Großhändlern vor.
        if ($value !== [] && ! array_is_list($value)) {
            $value = [$value];
        }

        return array_values(array_filter($value, 'is_array'));
    }

    private function url(mixed $value): ?string {
        $url = is_string($value) ? trim($value) : '';

        return str_starts_with($url, 'https://') || str_starts_with($url, 'http://') ? $url : null;
    }

    private function int(mixed $value): ?int {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) || is_float($value)) {
            $normalized = NumberHelper::normalizeDecimalStringOrNull((string) $value);

            return $normalized === null ? null : (int) round((float) $normalized);
        }

        return null;
    }
}
