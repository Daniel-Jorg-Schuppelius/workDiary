<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoicePlaneArticleCatalogSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\InvoicePlane\Services;

use App\Plugins\InvoicePlane\Schema\{ProductReader, VoucherReaderFactory};
use App\Plugins\Support\PluginSettingsResolver;
use App\Plugins\Support\TaskSync\{OffsetPage, RemotePages};
use App\Services\Finance\Accounting\Articles\{AccountingArticleCatalogSource, MirroredArticle};
use App\Services\Finance\Accounting\Vouchers\VoucherMirror;
use CommonToolkit\Helper\Data\StringHelper;

/**
 * InvoicePlane-Artikel (`ip_products`) als Katalogquelle `ipl` (Feature 185,
 * gap-09). Belegpositionen tragen `item_product_id` (seit 1.4.7), der
 * Katalogschlüssel hängt an der Fremd-ID; die SKU steht in `number`. Ein
 * Aktiv-Kennzeichen führt InvoicePlane nicht — alle Artikel gelten als aktiv.
 */
class InvoicePlaneArticleCatalogSource extends AccountingArticleCatalogSource {
    public const string PREFIX = 'ipl';

    public function pluginId(): string {
        return InvoicePlaneVoucherPullService::PLUGIN_ID;
    }

    public function prefix(): string {
        return self::PREFIX;
    }

    public function label(): string {
        return 'InvoicePlane';
    }

    public function isConfigured(int $organizationId): bool {
        return PluginSettingsResolver::for(InvoicePlaneVoucherPullService::PLUGIN_ID, $organizationId)->enabled() && $this->reader($organizationId) instanceof ProductReader;
    }

    /** Fabrik erst zur Aufrufzeit: die Quelle lebt ab dem Booten, die Bridge wird später gebunden (Tests, Pilot). */
    private function reader(int $organizationId): ?ProductReader {
        $reader = app(VoucherReaderFactory::class)->for($organizationId);

        return $reader instanceof ProductReader ? $reader : null;
    }

    public function fetch(int $organizationId): array {
        $reader = $this->reader($organizationId);
        if (! $reader instanceof ProductReader) {
            return [];
        }
        $limit = max(1, min(500, (int) config('invoiceplane.query.page_size', 500)));
        // Mehr als `max_rows` Zeilen liest die Bridge nicht: darüber ist das Lesen unvollständig.
        $maxPages = max(1, (int) ceil((int) config('invoiceplane.query.max_rows', 100_000) / $limit));
        $rows = RemotePages::offset(function (int $page) use ($reader, $limit): OffsetPage {
            $rows = $reader->products(($page - 1) * $limit, $limit);

            return new OffsetPage($rows, count($rows) >= $limit);
        }, $maxPages);
        $articles = [];
        foreach ($rows as $row) {
            $article = self::normalize($row);
            if ($article !== null) {
                $articles[] = $article;
            }
        }

        return $articles;
    }

    /** @param  array<string, mixed>  $row */
    private static function normalize(array $row): ?MirroredArticle {
        if (! is_numeric($row['product_id'] ?? null) || (int) $row['product_id'] <= 0) {
            return null;
        }
        $id = (string) (int) $row['product_id'];
        $sku = StringHelper::trimToNull(is_scalar($row['product_sku'] ?? null) ? (string) $row['product_sku'] : null);
        $family = StringHelper::trimToNull(is_scalar($row['family_name'] ?? null) ? (string) $row['family_name'] : null);
        $description = StringHelper::trimToNull(is_scalar($row['product_description'] ?? null) ? (string) $row['product_description'] : null);

        return new MirroredArticle(
            externalId: $id,
            name: StringHelper::trimToNull(is_scalar($row['product_name'] ?? null) ? (string) $row['product_name'] : null) ?? ($sku ?? ('#' . $id)),
            number: $sku,
            description: $family !== null ? ($description !== null ? $family . ' · ' . $description : $family) : $description,
            unitName: StringHelper::trimToNull(is_scalar($row['unit_name'] ?? null) ? (string) $row['unit_name'] : null),
            netUnitPrice: VoucherMirror::decimal($row['product_price'] ?? null, 4),
            vatRate: VoucherMirror::decimal($row['tax_rate_percent'] ?? null),
            kind: 'service',
            active: true,
        );
    }
}
