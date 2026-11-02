<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EasybillArticleCatalogSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Easybill\Services;

use App\Plugins\Easybill\Api\EasybillClientFactory;
use App\Plugins\Easybill\EasybillPlugin;
use App\Plugins\Support\TaskSync\{OffsetPage, RemotePages};
use App\Services\Finance\Accounting\Articles\{AccountingArticleCatalogSource, MirroredArticle};
use App\Services\Finance\Accounting\Vouchers\VoucherMirror;
use CommonToolkit\Enums\RoundingMode;
use CommonToolkit\Helper\Data\{NumberHelper, StringHelper};

/**
 * easybill-Artikel (`GET /positions`) als Katalogquelle `eb` (Feature 185,
 * gap-09). Belegpositionen tragen die Artikelnummer (`DocumentPosition.number`),
 * deshalb steht sie in `number`; Textpositionen (`type TEXT`) sind keine
 * Artikel. Preise kommen in Cent, bei `price_type BRUTTO` wird der
 * Nettopreis über den Steuersatz zurückgerechnet.
 */
class EasybillArticleCatalogSource extends AccountingArticleCatalogSource {
    public const PREFIX = 'eb';

    /** Seiten je Lauf (Limit 1000 je Seite laut API): darüber ist das Lesen unvollständig. */
    private const MAX_PAGES = 20;

    public function __construct(private readonly EasybillClientFactory $clients) {}

    public function pluginId(): string {
        return EasybillPlugin::ID;
    }

    public function prefix(): string {
        return self::PREFIX;
    }

    public function label(): string {
        return 'easybill';
    }

    public function isConfigured(int $organizationId): bool {
        return $this->clients->isConfigured($organizationId);
    }

    public function fetch(int $organizationId): array {
        $client = $this->clients->for($organizationId);
        $limit = max(1, min(1000, (int) config('plugins.easybill.page_size', 100)));
        $rows = RemotePages::offset(function (int $page) use ($client, $limit): OffsetPage {
            $result = $client->positions($page, $limit);

            return new OffsetPage($result['rows'], $result['rows'] !== [] && $page < $result['pages'], $result['total']);
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
        $type = strtoupper(trim((string) ($row['type'] ?? '')));
        $id = is_scalar($row['id'] ?? null) ? trim((string) $row['id']) : '';
        if ($id === '' || $type === 'TEXT') {
            return null;
        }
        $description = StringHelper::trimToNull(is_scalar($row['description'] ?? null) ? (string) $row['description'] : null);
        $number = StringHelper::trimToNull(is_scalar($row['number'] ?? null) ? (string) $row['number'] : null);
        $vat = is_numeric($row['vat_percent'] ?? null) ? NumberHelper::toUSFormat((float) $row['vat_percent'], 2) : null;
        $price = VoucherMirror::fromCents(is_numeric($row['sale_price'] ?? null) ? (int) round((float) $row['sale_price']) : null);
        $factor = $vat !== null ? NumberHelper::toUSFormat(1 + (float) $vat / 100, 6) : null;
        if (is_numeric($price) && is_numeric($factor) && strtoupper((string) ($row['price_type'] ?? 'NETTO')) === 'BRUTTO') {
            $price = NumberHelper::dividePrecise($price, $factor, 4, RoundingMode::HalfUp);
        }

        return new MirroredArticle(
            externalId: $id,
            name: StringHelper::lines($description ?? '', true)[0] ?? ($number ?? ('#' . $id)),
            number: $number,
            description: $description,
            unitName: StringHelper::trimToNull(is_scalar($row['unit'] ?? null) ? (string) $row['unit'] : null),
            netUnitPrice: $price !== null ? NumberHelper::toUSFormat($price, 4) : null,
            vatRate: $vat,
            kind: $type === 'PRODUCT' ? 'material' : 'service',
            active: ! (bool) ($row['archived'] ?? false),
        );
    }
}
