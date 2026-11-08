<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskArticleCatalogSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevDesk\Services;

use App\Plugins\SevDesk\Api\SevDeskClientFactory;
use App\Plugins\SevDesk\SevDeskPlugin;
use App\Plugins\Support\TaskSync\{OffsetPage, RemotePages};
use App\Services\Finance\Accounting\Articles\{AccountingArticleCatalogSource, MirroredArticle};
use App\Services\Finance\Accounting\Vouchers\VoucherMirror;
use CommonToolkit\Helper\Data\StringHelper;

/**
 * sevDesk-Artikel (`GET /Part`) als Katalogquelle `sd` (Feature 185, gap-09).
 * Belegpositionen tragen `part.id`, der Katalogschlüssel hängt an der
 * Fremd-ID; `status` 50 ist inaktiv, 100 aktiv; Artikel mit Lagerführung
 * (`stockEnabled`) werden als Material gebucht.
 */
class SevDeskArticleCatalogSource extends AccountingArticleCatalogSource {
    public const string PREFIX = 'sd';

    /** Seiten je Lauf (Limit bis 1000 je Seite laut API): darüber ist das Lesen unvollständig. */
    private const int MAX_PAGES = 20;

    public function __construct(private readonly SevDeskClientFactory $clients) {}

    public function pluginId(): string {
        return SevDeskPlugin::ID;
    }

    public function prefix(): string {
        return self::PREFIX;
    }

    public function label(): string {
        return 'sevDesk';
    }

    public function isConfigured(int $organizationId): bool {
        return $this->clients->isConfigured($organizationId);
    }

    public function fetch(int $organizationId): array {
        $client = $this->clients->for($organizationId);
        $limit = max(1, min(1000, (int) config('plugins.sevdesk.page_size', 100)));
        $rows = RemotePages::offset(function (int $page) use ($client, $limit): OffsetPage {
            $result = $client->parts(($page - 1) * $limit, $limit);
            $hasMore = $result['total'] !== null ? $page * $limit < $result['total'] : count($result['rows']) >= $limit;

            return new OffsetPage($result['rows'], $result['rows'] !== [] && $hasMore, $result['total']);
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
        if ($id === '') {
            return null;
        }
        $number = StringHelper::trimToNull(is_scalar($row['partNumber'] ?? null) ? (string) $row['partNumber'] : null);
        $price = VoucherMirror::decimal($row['priceNet'] ?? $row['price'] ?? null, 4);

        return new MirroredArticle(
            externalId: $id,
            name: StringHelper::trimToNull(is_scalar($row['name'] ?? null) ? (string) $row['name'] : null) ?? ($number ?? ('#' . $id)),
            number: $number,
            description: StringHelper::trimToNull(is_scalar($row['text'] ?? null) ? (string) $row['text'] : null),
            unitName: StringHelper::trimToNull(is_scalar(data_get($row, 'unity.name')) ? (string) data_get($row, 'unity.name') : null),
            netUnitPrice: $price,
            vatRate: VoucherMirror::decimal($row['taxRate'] ?? null),
            kind: StringHelper::parseBool($row['stockEnabled'] ?? null, false) === true ? 'material' : 'service',
            active: (int) ($row['status'] ?? 100) !== 50,
        );
    }
}
