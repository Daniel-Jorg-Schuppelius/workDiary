<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleMirror.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Accounting\Articles;

use App\Models\Finance\{AccountingArticle, AccountingVoucher, AccountingVoucherLine};
use App\Services\Platform\Catalog\ArticleCatalog;
use Illuminate\Support\Carbon;

/**
 * Schreibstelle des Artikelspiegels (Feature 185, gap-09): je Organisation
 * und Programm landen die gelesenen Artikel in `accounting_articles`, nicht
 * mehr geführte werden archiviert (nie gelöscht — alte Belegpositionen
 * zeigen weiter auf sie), und Belegpositionen, die bisher nur
 * `<plugin>:<Fremd-ID>` trugen, bekommen den Katalogschlüssel der Quelle.
 */
class ArticleMirror {
    /** @var array<string, AccountingArticleCatalogSource>|null Plugin-ID → Quelle */
    private ?array $byPlugin = null;

    public function __construct(private readonly ArticleCatalog $catalog) {}

    /** @return list<AccountingArticleCatalogSource> registrierte Quellen des Artikelspiegels */
    public function sources(): array {
        return array_values($this->sourcesByPlugin());
    }

    public function sourceFor(string $pluginId): ?AccountingArticleCatalogSource {
        return $this->sourcesByPlugin()[$pluginId] ?? null;
    }

    /**
     * Liest alle Artikel der Quelle und spiegelt sie; ein unvollständiges
     * Lesen wirft vor dem ersten Schreiben.
     *
     * @return array{read: int, created: int, updated: int, archived: int, relinked: int}
     */
    public function sync(int $organizationId, AccountingArticleCatalogSource $source): array {
        $articles = $source->fetch($organizationId);
        $counters = ['read' => count($articles), 'created' => 0, 'updated' => 0, 'archived' => 0, 'relinked' => 0];
        $now = Carbon::now();
        $seen = [];
        $existing = $this->query($organizationId, $source->pluginId())->get()->keyBy('external_id');
        foreach ($articles as $article) {
            $seen[] = $article->externalId;
            $attributes = [
                'number' => $article->number !== null ? mb_substr($article->number, 0, 128) : null,
                'name' => mb_substr($article->name, 0, 255),
                'description' => $article->description,
                'unit_name' => $article->unitName !== null ? mb_substr($article->unitName, 0, 32) : null,
                'net_unit_price' => $article->netUnitPrice,
                'currency' => $article->currency !== '' ? $article->currency : 'EUR',
                'vat_rate' => $article->vatRate,
                'kind' => $article->kind === 'material' ? 'material' : 'service',
                'archived_at' => $article->active ? null : $now,
                'synced_at' => $now,
            ];
            $current = $existing->get($article->externalId);
            if ($current === null) {
                AccountingArticle::query()->create($attributes + [
                    'organization_id' => $organizationId,
                    'plugin_id' => $source->pluginId(),
                    'external_id' => mb_substr($article->externalId, 0, 128),
                ]);
                $counters['created']++;
            } else {
                // Archivierungszeitpunkt bleibt, solange der Artikel archiviert ist.
                if (! $article->active && $current->archived_at !== null) {
                    unset($attributes['archived_at']);
                }
                $current->fill($attributes)->save();
                if ($current->wasChanged(array_keys(array_diff_key($attributes, ['synced_at' => true])))) {
                    $counters['updated']++;
                }
            }
        }
        $counters['archived'] = $this->query($organizationId, $source->pluginId())
            ->whereNull('archived_at')
            ->whereNotIn('external_id', $seen)
            ->update(['archived_at' => $now]);
        $counters['relinked'] = $this->relinkLines($organizationId, $source);

        return $counters;
    }

    /**
     * Katalogschlüssel zu den Artikelkennungen gespiegelter Belegpositionen
     * (Fremd-ID oder Nummer, je nachdem, was das Programm an der Position
     * führt). Unbekannte Kennungen fehlen im Ergebnis.
     *
     * @param  list<string>  $externalArticleIds
     * @return array<string, string> Kennung → Katalogschlüssel
     */
    public function keysFor(int $organizationId, string $pluginId, array $externalArticleIds): array {
        $source = $this->sourceFor($pluginId);
        $ids = array_values(array_unique(array_filter($externalArticleIds, static fn (string $id): bool => $id !== '')));
        if ($source === null || $ids === []) {
            return [];
        }
        $keys = [];
        $rows = $this->query($organizationId, $pluginId)
            ->where(fn ($q) => $q->whereIn('external_id', $ids)->orWhereIn('number', $ids))
            ->orderBy('id')
            ->get(['id', 'external_id', 'number']);
        // Fremd-ID schlägt Nummer: erst die Nummern, dann überschreiben die IDs.
        foreach ($rows as $row) {
            if ($row->number !== null && in_array($row->number, $ids, true) && ! isset($keys[$row->number])) {
                $keys[$row->number] = ArticleCatalog::key($source->prefix(), (int) $row->id);
            }
        }
        foreach ($rows as $row) {
            if (in_array($row->external_id, $ids, true)) {
                $keys[$row->external_id] = ArticleCatalog::key($source->prefix(), (int) $row->id);
            }
        }

        return $keys;
    }

    /** Belegpositionen des Programms ohne Katalogschlüssel der Quelle nachziehen. */
    private function relinkLines(int $organizationId, AccountingArticleCatalogSource $source): int {
        $vouchers = AccountingVoucher::query()->withoutGlobalScopes()
            ->select('id')
            ->where('organization_id', $organizationId)
            ->where('plugin_id', $source->pluginId());
        $pending = AccountingVoucherLine::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereIn('voucher_id', $vouchers)
            ->whereNotNull('external_article_id')
            ->where(fn ($q) => $q->whereNull('article_ref')->orWhere('article_ref', 'not like', $source->prefix() . ':%'))
            ->get(['id', 'external_article_id']);
        if ($pending->isEmpty()) {
            return 0;
        }
        $keys = $this->keysFor($organizationId, $source->pluginId(), array_values(array_map(static fn (AccountingVoucherLine $line): string => (string) $line->external_article_id, $pending->all())));
        $relinked = 0;
        foreach ($pending->groupBy('external_article_id') as $externalId => $lines) {
            $key = $keys[(string) $externalId] ?? null;
            if ($key !== null) {
                $relinked += AccountingVoucherLine::query()->withoutGlobalScopes()->whereKey($lines->pluck('id')->all())->update(['article_ref' => $key]);
            }
        }

        return $relinked;
    }

    /** @return \Illuminate\Database\Eloquent\Builder<AccountingArticle> */
    private function query(int $organizationId, string $pluginId) {
        return AccountingArticle::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', $pluginId);
    }

    /** @return array<string, AccountingArticleCatalogSource> */
    private function sourcesByPlugin(): array {
        if ($this->byPlugin === null) {
            $this->byPlugin = [];
            foreach ($this->catalog->sources() as $source) {
                if ($source instanceof AccountingArticleCatalogSource) {
                    $this->byPlugin[$source->pluginId()] = $source;
                }
            }
        }

        return $this->byPlugin;
    }
}
