<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleCatalog.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Platform\Catalog;

use App\Modules\ModuleRegistry;

/**
 * Artikelkatalog mit Quellen (Phase 125, MVP-1025): Tabellen verweisen über
 * einen quellqualifizierten Schlüssel (`art:12`, `lex:34`) auf Artikel —
 * dasselbe Format wie `MirrorLine::$articleKey` — statt über anbieterbezogene
 * Spalten. Erweiterungspunkt der Plattform: Module melden ihre Quelle in
 * `extensions()` an, Plugins registrieren sie beim Booten. Singleton ohne
 * Cache (lebt auch in Queue-Prozessen); Aufrufer laden in Schleifen gebündelt
 * über {@see findMany()}.
 */
final class ArticleCatalog {
    /** @var array<string, ArticleCatalogSource>|null null = Manifest-Quellen noch nicht geladen */
    private ?array $sources = null;

    public function register(ArticleCatalogSource $source): void {
        $this->load();
        $this->sources[$source->prefix()] = $source;
    }

    /** @return list<ArticleCatalogSource> nach Vorrang */
    public function sources(): array {
        $sources = array_values($this->load());
        usort($sources, static fn (ArticleCatalogSource $a, ArticleCatalogSource $b): int => $a->priority() <=> $b->priority());

        return $sources;
    }

    public static function key(string $prefix, int $id): string {
        return $prefix . ':' . $id;
    }

    /** @return array{0: string, 1: int}|null */
    public static function parse(?string $key): ?array {
        if ($key === null || preg_match('/^([a-z][a-z0-9_]*):(\d+)$/', $key, $m) !== 1) {
            return null;
        }

        return [$m[1], (int) $m[2]];
    }

    public function find(int $organizationId, ?string $key): ?CatalogArticle {
        return $key === null ? null : ($this->findMany($organizationId, [$key])[$key] ?? null);
    }

    /**
     * @param  iterable<string|null>  $keys
     * @return array<string, CatalogArticle> Schlüssel → Eintrag
     */
    public function findMany(int $organizationId, iterable $keys): array {
        $byPrefix = [];
        foreach ($keys as $key) {
            $parsed = self::parse($key);
            if ($parsed !== null && isset($this->load()[$parsed[0]])) {
                $byPrefix[$parsed[0]][$parsed[1]] = $parsed[1];
            }
        }

        $found = [];
        foreach ($byPrefix as $prefix => $ids) {
            foreach ($this->load()[$prefix]->find($organizationId, array_values($ids)) as $article) {
                $found[$article->key] = $article;
            }
        }

        return $found;
    }

    /** @return list<CatalogArticle> aktive Artikel aller Quellen, nach Vorrang */
    public function active(int $organizationId): array {
        $articles = [];
        foreach ($this->sources() as $source) {
            foreach ($source->active($organizationId) as $article) {
                $articles[] = $article;
            }
        }

        return $articles;
    }

    /** Formularschlüssel `<quelle>:<sqid>` → Eintrag der Organisation, sonst null. */
    public function fromFormKey(int $organizationId, ?string $formKey): ?CatalogArticle {
        if ($formKey === null || ! str_contains($formKey, ':')) {
            return null;
        }
        [$prefix, $token] = explode(':', $formKey, 2);
        $source = $this->load()[$prefix] ?? null;
        $id = $source?->decode($token);

        return $id === null ? null : $this->find($organizationId, self::key($prefix, $id));
    }

    /** @return array<string, ArticleCatalogSource> */
    private function load(): array {
        if ($this->sources === null) {
            $this->sources = [];
            foreach (app(ModuleRegistry::class)->extensions(ArticleCatalogSource::class) as $class) {
                $source = app($class);
                $this->sources[$source->prefix()] = $source;
            }
        }

        return $this->sources;
    }
}
