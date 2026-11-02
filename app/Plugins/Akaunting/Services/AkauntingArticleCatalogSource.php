<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AkauntingArticleCatalogSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Akaunting\Services;

use App\Plugins\Akaunting\{AkauntingConfig, AkauntingPlugin};
use App\Plugins\Akaunting\Api\AkauntingClientFactory;
use App\Plugins\Support\TaskSync\{OffsetPage, RemotePages};
use App\Services\Finance\Accounting\Articles\{AccountingArticleCatalogSource, MirroredArticle};
use App\Services\Finance\Accounting\Vouchers\VoucherMirror;
use CommonToolkit\Helper\Data\StringHelper;

/**
 * Akaunting-Artikel (`GET /api/items`) als Katalogquelle `aka` (Feature 185,
 * gap-09). Belegpositionen tragen `item_id`, der Katalogschlüssel hängt an
 * der Fremd-ID; `enabled` ist die Aktivität, `type` product/service die
 * Positionsart, der Steuersatz kommt aus der ersten zugeordneten Steuer.
 * Eine Nummer oder Einheit führt Akaunting am Artikel nicht.
 */
class AkauntingArticleCatalogSource extends AccountingArticleCatalogSource {
    public const PREFIX = 'aka';

    /** Seiten je Lauf: darüber ist das Lesen unvollständig. */
    private const MAX_PAGES = 50;

    public function __construct(private readonly AkauntingClientFactory $clients) {}

    public function pluginId(): string {
        return AkauntingPlugin::ID;
    }

    public function prefix(): string {
        return self::PREFIX;
    }

    public function label(): string {
        return 'Akaunting';
    }

    public function isConfigured(int $organizationId): bool {
        return AkauntingConfig::resolve($organizationId)['enabled'] && $this->clients->isConfigured($organizationId);
    }

    public function fetch(int $organizationId): array {
        $client = $this->clients->for($organizationId);
        $rows = RemotePages::offset(function (int $page) use ($client): OffsetPage {
            $result = $client->items($page);

            return new OffsetPage($result['rows'], $result['rows'] !== [] && $page < $result['last_page'], $result['total']);
        }, self::MAX_PAGES);
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
        $id = is_scalar($row['id'] ?? null) ? trim((string) $row['id']) : '';
        if ($id === '' || $id === '0') {
            return null;
        }
        $taxes = data_get($row, 'taxes.data');
        $rate = null;
        foreach (is_array($taxes) ? $taxes : [] as $tax) {
            $rate = VoucherMirror::decimal(data_get($tax, 'tax.rate'));
            if ($rate !== null) {
                break;
            }
        }

        return new MirroredArticle(
            externalId: $id,
            name: StringHelper::trimToNull(is_scalar($row['name'] ?? null) ? (string) $row['name'] : null) ?? ('#' . $id),
            number: null,
            description: StringHelper::trimToNull(is_scalar($row['description'] ?? null) ? (string) $row['description'] : null),
            unitName: null,
            netUnitPrice: VoucherMirror::decimal($row['sale_price'] ?? null, 4),
            vatRate: $rate,
            kind: strtolower((string) ($row['type'] ?? 'service')) === 'product' ? 'material' : 'service',
            active: StringHelper::parseBool($row['enabled'] ?? null, true) !== false,
        );
    }
}
