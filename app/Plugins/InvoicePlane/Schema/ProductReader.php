<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProductReader.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\InvoicePlane\Schema;

/**
 * Lesender Zugriff auf den Artikelstamm einer InvoicePlane-Instanz
 * (Feature 086, `read_allowlist` products/families/units/tax_rates; Feature
 * 185, gap-09). Eigenes Interface neben {@see VoucherReader}: ein Leser ohne
 * Artikelzugriff bleibt ein gültiger Belegleser.
 */
interface ProductReader {
    /**
     * Artikel aus `ip_products`, seitenweise nach `product_id`, mit
     * Familie (`ip_families.family_name`), Einheit (`ip_units.unit_name`) und
     * Steuersatz (`ip_tax_rates.tax_rate_percent`): `product_id`, `product_sku`,
     * `product_name`, `product_description`, `product_price`, `family_name`,
     * `unit_name`, `tax_rate_percent` (Spalten laut Setup-SQL 1.2.0–1.5.0).
     *
     * @return list<array<string, mixed>>
     */
    public function products(int $offset, int $limit): array;
}
