<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceNinjaArticleCatalogSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\InvoiceNinja\Services;

use App\Plugins\InvoiceNinja\Api\InvoiceNinjaClientFactory;
use App\Plugins\InvoiceNinja\{InvoiceNinjaConfig, InvoiceNinjaPlugin};
use App\Plugins\Support\TaskSync\{OffsetPage, RemotePages};
use App\Services\Finance\Accounting\Articles\{AccountingArticleCatalogSource, MirroredArticle};
use App\Services\Finance\Accounting\Vouchers\VoucherMirror;
use CommonToolkit\Helper\Data\StringHelper;

/**
 * Invoice-Ninja-Produkte (`GET /api/v1/products`) als Katalogquelle `inj`
 * (Feature 185, gap-09). Belegpositionen tragen nur den `product_key`, nicht
 * die gehashte ID — deshalb steht er in `number`. Archivierte und gelöschte
 * Produkte werden mitgelesen (alte Positionen zeigen weiter auf sie), aber
 * als inaktiv geführt. Eine Einheit kennt das Produkt nicht.
 */
class InvoiceNinjaArticleCatalogSource extends AccountingArticleCatalogSource {
    public const string PREFIX = 'inj';

    /** Seiten je Lauf: darüber ist das Lesen unvollständig. */
    private const int MAX_PAGES = 50;

    public function __construct(private readonly InvoiceNinjaClientFactory $clients) {}

    public function pluginId(): string {
        return InvoiceNinjaPlugin::ID;
    }

    public function prefix(): string {
        return self::PREFIX;
    }

    public function label(): string {
        return 'Invoice Ninja';
    }

    public function isConfigured(int $organizationId): bool {
        return InvoiceNinjaConfig::resolve($organizationId)['enabled'] && $this->clients->isConfigured($organizationId);
    }

    public function fetch(int $organizationId): array {
        $client = $this->clients->for($organizationId);
        $currency = $this->companyCurrency($client);
        // Nach ID geblättert: eine Änderung während des Laufs verschiebt sonst die Seiten.
        $query = ['status' => 'active,archived,deleted', 'sort' => 'id|asc'];
        $rows = RemotePages::offset(function (int $page) use ($client, $query): OffsetPage {
            $result = $client->page('products', $query, $page);

            return new OffsetPage($result['rows'], $result['rows'] !== [] && $page < $result['total_pages']);
        }, self::MAX_PAGES);
        $articles = [];
        foreach ($rows as $row) {
            $article = self::normalize($row, $currency);
            if ($article !== null) {
                $articles[] = $article;
            }
        }

        return $articles;
    }

    /** Firmenwährung des Tokens; Produktpreise tragen keine eigene. */
    private function companyCurrency(\App\Plugins\InvoiceNinja\Api\InvoiceNinjaClient $client): string {
        $currencyId = $client->company()['currency_id'];

        return $currencyId !== null ? ($client->currencies()[$currencyId] ?? 'EUR') : 'EUR';
    }

    /** @param  array<string, mixed>  $row */
    private static function normalize(array $row, string $currency): ?MirroredArticle {
        $id = is_scalar($row['id'] ?? null) ? trim((string) $row['id']) : '';
        $key = StringHelper::trimToNull(is_scalar($row['product_key'] ?? null) ? (string) $row['product_key'] : null);
        if ($id === '' || $key === null) {
            return null;
        }
        $archived = is_numeric($row['archived_at'] ?? null) && (int) $row['archived_at'] > 0;

        return new MirroredArticle(
            externalId: $id,
            name: $key,
            number: $key,
            description: StringHelper::trimToNull(is_scalar($row['notes'] ?? null) ? (string) $row['notes'] : null),
            unitName: null,
            netUnitPrice: VoucherMirror::decimal($row['price'] ?? null, 4),
            currency: $currency,
            vatRate: VoucherMirror::decimal($row['tax_rate1'] ?? null),
            kind: 'service',
            active: ! $archived && StringHelper::parseBool($row['is_deleted'] ?? null, false) !== true,
        );
    }
}
