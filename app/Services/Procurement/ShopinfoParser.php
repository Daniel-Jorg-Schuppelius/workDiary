<?php
/*
 * Created on   : Sat Jun 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShopinfoParser.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement;

use CommonToolkit\Helper\Data\XmlHelper;
use RuntimeException;
use SimpleXMLElement;

/**
 * Liest eine `shopinfo.xml` (Shop-Discovery, Feature 050, MVP-092) und leitet
 * Mapping-Vorschläge sowie Katalog-Eckdaten ab. Die Download-URL wird NICHT
 * automatisch verfolgt — sie ist nur ein Hinweis, den ein Admin freigeben muss.
 * Die Vorschläge sind nie autoritativ; sie werden gegen die tatsächlich geladene
 * Datei validiert (durch den CSV-Preflight).
 */
class ShopinfoParser {
    /** Shopinfo-Spaltentyp (uppercase) => internes Zielfeld. */
    private const TYPE_MAP = [
        'ARTICLE_NUMBER' => 'external_no',
        'ARTICLENO' => 'external_no',
        'SUPPLIER_AID' => 'external_no',
        'ORDER_NUMBER' => 'external_no',
        'DESCRIPTION' => 'name',
        'SHORTDESCRIPTION' => 'name',
        'SHORT_DESCRIPTION' => 'name',
        'NAME' => 'name',
        'LONGDESCRIPTION' => 'description',
        'LONG_DESCRIPTION' => 'description',
        'PRICE' => 'purchase_price',
        'NETPRICE' => 'purchase_price',
        'PURCHASEPRICE' => 'purchase_price',
        'EAN' => 'gtin',
        'GTIN' => 'gtin',
        'MANUFACTURER' => 'manufacturer',
        'BRAND' => 'manufacturer',
        'MANUFACTURER_AID' => 'manufacturer_no',
        'MPN' => 'manufacturer_no',
        'CATEGORY' => 'category',
        'PRODUCTGROUP' => 'category',
        'CURRENCY' => 'currency',
    ];

    /**
     * @return array{catalog_url: ?string, charset: ?string, delimiter: ?string, mapping: array<string, string>}
     *
     * @throws RuntimeException Bei ungültigem XML.
     */
    public function parse(string $content): array {
        // Feature 052: XXE-sicheres Laden über das Common-Toolkit (LIBXML_NONET).
        $xml = XmlHelper::safeLoadString($content);

        if (! $xml instanceof SimpleXMLElement) {
            throw new RuntimeException((string) __('procurement.catalog.error.invalid_xml'));
        }

        return [
            'catalog_url' => $this->first($xml, ['URL', 'CATALOGURL', 'CATALOG_URL', 'DOWNLOAD']),
            'charset' => $this->first($xml, ['CHARSET', 'CHARACTERSET', 'ENCODING']),
            'delimiter' => $this->first($xml, ['DELIMITER', 'SEPARATOR', 'FIELD_SEPARATOR']),
            'mapping' => $this->mapping($xml),
        ];
    }

    /**
     * @return array<string, string>  Zielfeld => CSV-Spaltenname
     */
    private function mapping(SimpleXMLElement $xml): array {
        $columns = $xml->xpath('//*[local-name()="COLUMN"]') ?: [];
        $mapping = [];

        foreach ($columns as $column) {
            $type = strtoupper(trim((string) ($column['type'] ?? '')));
            $field = self::TYPE_MAP[$type] ?? null;
            if ($field === null || isset($mapping[$field])) {
                continue;
            }

            $name = trim((string) ($column['name'] ?? ''));
            if ($name === '') {
                $number = trim((string) ($column['number'] ?? ''));
                $name = $number !== '' ? 'col' . $number : '';
            }
            if ($name !== '') {
                $mapping[$field] = $name;
            }
        }

        return $mapping;
    }

    /**
     * Erstes nicht-leeres Element (an beliebiger Stelle) aus einer Namensliste.
     *
     * @param  list<string>  $names
     */
    private function first(SimpleXMLElement $xml, array $names): ?string {
        foreach ($names as $name) {
            $nodes = $xml->xpath('//*[local-name()="' . $name . '"]') ?: [];
            if (isset($nodes[0])) {
                $value = trim((string) $nodes[0]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }
}
