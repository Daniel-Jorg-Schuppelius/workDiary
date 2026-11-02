<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingArticleCatalogSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Accounting\Articles;

use App\Models\Finance\AccountingArticle;
use App\Services\Platform\Catalog\{ArticleCatalog, ArticleCatalogSource, CatalogArticle};
use App\Support\Sqid;
use Illuminate\Database\Eloquent\Builder;

/**
 * Katalogquelle eines Buchhaltungsprogramms über den gemeinsamen Artikelspiegel
 * `accounting_articles` (Feature 185, gap-09). Das Plugin liefert nur Präfix,
 * Bezeichnung, Bereitschaft und {@see fetch()} — das vollständige Lesen seiner
 * Artikel; Spiegeln, Archivieren und Schlüssel macht {@see ArticleMirror}.
 * Vorrang zwischen Lexoffice (10) und Artikelstamm (20): bei externer
 * Rechnungshoheit stehen die Abo-Produkte im Buchhaltungsprogramm.
 */
abstract class AccountingArticleCatalogSource implements ArticleCatalogSource {
    /** Plugin-ID, unter der die Belege des Programms in `accounting_vouchers` liegen. */
    abstract public function pluginId(): string;

    /** Ist das Lesen für diese Organisation eingerichtet (Zugang + Schalter)? */
    abstract public function isConfigured(int $organizationId): bool;

    /**
     * Alle Artikel des Programms, vollständig gelesen: ein erreichtes
     * Seitenlimit wirft {@see \App\Plugins\Support\TaskSync\IncompleteRemoteReadException},
     * damit der Spiegel nichts archiviert, was nur nicht gelesen wurde.
     *
     * @return list<MirroredArticle>
     */
    abstract public function fetch(int $organizationId): array;

    public function priority(): int {
        return 12;
    }

    public function find(int $organizationId, array $ids): array {
        $found = [];
        foreach ($this->query($organizationId)->whereKey($ids)->get() as $article) {
            $found[(int) $article->id] = $this->toCatalog($article);
        }

        return $found;
    }

    public function active(int $organizationId): array {
        $articles = [];
        foreach ($this->query($organizationId)->active()->orderBy('name')->limit(2000)->get() as $article) {
            $articles[] = $this->toCatalog($article);
        }

        return $articles;
    }

    public function decode(string $token): ?int {
        return Sqid::decode(AccountingArticle::class, $token);
    }

    /** @return Builder<AccountingArticle> */
    protected function query(int $organizationId): Builder {
        return AccountingArticle::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', $this->pluginId());
    }

    protected function toCatalog(AccountingArticle $article): CatalogArticle {
        return new CatalogArticle(
            key: ArticleCatalog::key($this->prefix(), (int) $article->id),
            formKey: $this->prefix() . ':' . Sqid::encode(AccountingArticle::class, (int) $article->id),
            source: $this->prefix(),
            sourceLabel: $this->label(),
            id: (int) $article->id,
            name: (string) $article->name,
            number: $article->number,
            unitName: $article->unit_name,
            netPrice: $article->net_unit_price,
            active: $article->archived_at === null,
            description: $article->description,
            vatRate: $article->vat_rate,
            itemType: $article->kind === 'material' ? 'material' : 'service',
        );
    }
}
