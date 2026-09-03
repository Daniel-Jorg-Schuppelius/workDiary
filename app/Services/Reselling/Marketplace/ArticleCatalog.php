<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleCatalog.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use App\Models\LexofficeArticle;
use CommonToolkit\ValueObjects\Money;

/**
 * Der eigene Artikelstamm (Lexoffice, lokal gespiegelt) als Produkt- und
 * Preisquelle: Eine Rechnungsposition, die aus einem Artikel entstand, trägt
 * dessen ID oder die Artikelnummer in eckigen Klammern im Namen
 * („[DJS-IT-MCLD-EX01P1] Exchange Online"). Der Artikelname ist dann der
 * verlässliche Produkttext, die Einheit („Monat") sagt, dass die Menge in
 * Monaten steht, und der Artikelpreis ist der aktuelle Verkaufspreis für die
 * Preisprüfung — auch ohne Rechnung im Zeitraum.
 */
final class ArticleCatalog {
    /** @var list<ArticleEntry> */
    private array $entries = [];

    /** @var array<string, ArticleEntry> */
    private array $byExternalId = [];

    /** @var array<string, ArticleEntry> */
    private array $byNumber = [];

    /** @var array<string, ArticleEntry> */
    private array $byName = [];

    /**
     * @param  iterable<ArticleEntry>  $entries
     */
    public function __construct(iterable $entries = [], private readonly ProductNameMatcher $matcher = new ProductNameMatcher()) {
        foreach ($entries as $entry) {
            $this->entries[] = $entry;
            if ($entry->externalId !== '') {
                $this->byExternalId[$entry->externalId] ??= $entry;
            }
            if ($entry->number !== '') {
                $this->byNumber[mb_strtolower($entry->number)] ??= $entry;
            }
            $this->byName[ProductNameMatcher::normalize($entry->name)] ??= $entry;
        }
    }

    public static function forOrganization(int $organizationId): self {
        $entries = [];
        $articles = LexofficeArticle::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereNull('archived_at')
            ->get();
        foreach ($articles as $article) {
            // Der Artikelpreis ist mit vier Nachkommastellen gespeichert —
            // für den Vergleich mit Gebühren und Rechnungen auf Cent bringen.
            $price = $article->net_unit_price;
            $entries[] = new ArticleEntry(
                externalId: (string) $article->external_id,
                number: (string) ($article->article_number ?? ''),
                name: (string) $article->name,
                unit: (string) ($article->unit_name ?? ''),
                netUnitPrice: $price instanceof Money ? $price->withScale(2) : null,
            );
        }

        return new self($entries);
    }

    public static function empty(): self {
        return new self();
    }

    public function isEmpty(): bool {
        return $this->entries === [];
    }

    /**
     * Artikelnummer aus „[DJS-IT-MCLD-EX01P1] Exchange Online".
     */
    public static function numberFromText(string $text): string {
        return preg_match('/^\s*\[([A-Za-z0-9._\-\/]{3,})\]/', $text, $match) === 1 ? $match[1] : '';
    }

    public function forLine(InvoiceLine $line): ?ArticleEntry {
        if ($line->articleId !== '' && isset($this->byExternalId[$line->articleId])) {
            return $this->byExternalId[$line->articleId];
        }
        $number = self::numberFromText($line->name);
        if ($number !== '' && isset($this->byNumber[mb_strtolower($number)])) {
            return $this->byNumber[mb_strtolower($number)];
        }

        return $this->byName[ProductNameMatcher::normalize($line->name)] ?? null;
    }

    /**
     * Artikel, die eine Marketplace-Edition abbilden.
     *
     * @return list<ArticleEntry>
     */
    public function forEdition(string $edition): array {
        return array_values(array_filter($this->entries, fn(ArticleEntry $entry): bool => $this->matcher->matches($edition, $entry->name)));
    }
}
