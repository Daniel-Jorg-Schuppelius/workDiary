<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxArticleCatalogSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax\Services;

use App\Plugins\OrgaMax\Api\OrgaMaxClientFactory;
use App\Plugins\OrgaMax\Models\OrgaMaxConnection;
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use App\Plugins\Support\PluginSettingsResolver;
use App\Plugins\Support\TaskSync\{OffsetPage, RemotePages};
use App\Services\Finance\Accounting\Articles\{AccountingArticleCatalogSource, MirroredArticle};
use App\Services\Finance\Accounting\Vouchers\VoucherMirror;
use CommonToolkit\Helper\Data\StringHelper;
use Orgamax\API\Endpoints\ArticlesEndpoint;
use Orgamax\Entities\Articles\Article;

/**
 * orgaMAX-Artikel (`GET /article/`, `ArticleData`) als Katalogquelle `om`
 * (MVP-1501). Belegpositionen tragen die Artikel-ID in `metaData.id`, der
 * Katalogschlüssel hängt an ihr; `price` ist der Nettopreis, Artikel mit
 * Lagerführung (`isStockManaged`) werden als Material gebucht. Ein
 * Archivkennzeichen führt die API nicht; jeder gelesene Artikel ist aktiv.
 */
final class OrgaMaxArticleCatalogSource extends AccountingArticleCatalogSource {
    public const string PREFIX = 'om';

    /** Seiten je Lauf: darüber ist das Lesen unvollständig. */
    private const int MAX_PAGES = 50;

    public function __construct(private readonly OrgaMaxClientFactory $clients) {}

    public function pluginId(): string {
        return OrgaMaxPlugin::ID;
    }

    public function prefix(): string {
        return self::PREFIX;
    }

    public function label(): string {
        return 'orgaMAX';
    }

    /** Plugin eingeschaltet, Verbindung aktiv und die Capability „Artikel“ freigegeben. */
    public function isConfigured(int $organizationId): bool {
        $connection = $this->connection($organizationId);

        return $connection !== null && $connection->capabilityEnabled('articles') && PluginSettingsResolver::for(OrgaMaxPlugin::ID, $organizationId)->enabled();
    }

    public function fetch(int $organizationId): array {
        $connection = $this->connection($organizationId);
        if ($connection === null) {
            return [];
        }
        $endpoint = new ArticlesEndpoint($this->clients->for($connection));
        $limit = max(1, (int) config('plugins.orgamax.page_size', 100));
        $rows = RemotePages::offset(static function (int $page) use ($endpoint, $limit): OffsetPage {
            $list = $endpoint->search(['offset' => ($page - 1) * $limit, 'limit' => $limit]);
            $values = $list?->getValues() ?? [];
            $total = $list?->getMeta()?->getTotalCount();
            $hasMore = $total !== null ? $page * $limit < $total : count($values) >= $limit;

            return new OffsetPage(array_values($values), $values !== [] && $hasMore, $total);
        }, self::MAX_PAGES);

        $articles = [];
        foreach ($rows as $row) {
            if ($row->getId() !== null) {
                $articles[] = self::normalize($row);
            }
        }

        return $articles;
    }

    private function connection(int $organizationId): ?OrgaMaxConnection {
        $connection = OrgaMaxConnection::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->first();

        return $connection instanceof OrgaMaxConnection && $connection->isActive() ? $connection : null;
    }

    private static function normalize(Article $article): MirroredArticle {
        $number = StringHelper::trimToNull($article->getNumber());

        return new MirroredArticle(
            externalId: (string) $article->getId(),
            name: StringHelper::trimToNull($article->getTitle()) ?? ($number ?? ('#' . $article->getId())),
            number: $number,
            description: StringHelper::trimToNull($article->getDescription()),
            unitName: StringHelper::trimToNull($article->getUnit()),
            netUnitPrice: VoucherMirror::decimal($article->getPrice(), 4),
            vatRate: VoucherMirror::decimal($article->getVatPercent()),
            kind: $article->isStockManaged() ? 'material' : 'service',
        );
    }
}
