<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleCatalogSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Platform\Catalog;

/**
 * Eine Quelle des Artikelkatalogs (MVP-1025). Der Kern registriert den
 * Artikelstamm, Buchhaltungs-Plugins ihre Artikel beim Booten.
 */
interface ArticleCatalogSource {
    /** Stabiles Präfix des Katalogschlüssels (`art`, `lex`, …) — nie ändern, es steht in Datenbanken. */
    public function prefix(): string;

    public function label(): string;

    /** Vorrang beim Namensabgleich, kleiner zuerst. */
    public function priority(): int;

    /**
     * @param  list<int>  $ids
     * @return array<int, CatalogArticle> ID → Eintrag, fremde oder fehlende IDs fehlen
     */
    public function find(int $organizationId, array $ids): array;

    /** @return list<CatalogArticle> aktive Artikel für Auswahl und Namensabgleich */
    public function active(int $organizationId): array;

    /** Formular-Token (Sqid der Quelle) → ID; null, wenn es nicht dekodiert. */
    public function decode(string $token): ?int;
}
