<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ObsidianVaultReader.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Collections\Import;

use App\Models\CloudIntake\CloudDocumentConnection;
use App\Plugins\Contracts\DocumentIntakeSource;
use App\Plugins\Support\Intake\IntakeItem;
use Carbon\CarbonImmutable;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Liest einen Obsidian-Tresor über eine vorhandene Ordner-Anbindung des
 * Cloud-Dokumenteingangs (MVP-815, Feature 155) — lesend, über denselben
 * Vertrag {@see DocumentIntakeSource}, ohne Checkpoint der Anbindung zu
 * berühren.
 *
 * Ein Tresor ist ein Ordner mit Markdown-Dateien: YAML-Kopf (`tags`, `title`)
 * und `#schlagwort` im Text werden Schlagwörter, `[[Wikilinks]]` Verweise,
 * Unterordner Sammlungen. `.obsidian/` und `.trash/` bleiben draußen.
 */
class ObsidianVaultReader {
    /** Größere Dateien sind keine Notizen, sondern Anhänge oder Exporte. */
    public const MAX_FILE_BYTES = 1_048_576;

    private const MAX_PAGES = 500;

    /**
     * Schon übernommene Dateien (`$known`) werden nicht erneut geladen — sie
     * zählen nicht gegen `$limit` und liefern nur ihre Namen für Verweise.
     *
     * @param  callable(string): bool  $known
     * @return array{documents: list<ImportedDocument>, limited: bool}
     */
    public function documents(CloudDocumentConnection $connection, DocumentIntakeSource $source, string $vaultPath, int $limit, callable $known): array {
        $prefix = self::normalizePath($vaultPath);
        $items = [];
        $checkpoint = null;
        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $changes = $source->intakeChanges($connection, $checkpoint);
            foreach ($changes->items as $item) {
                if ($this->belongsToVault($item, $prefix)) {
                    $items[] = $item;
                }
            }
            if (! $changes->hasMore) {
                break;
            }
            $checkpoint = $changes->checkpoint;
        }

        usort($items, static fn (IntakeItem $a, IntakeItem $b): int => strcmp($a->path, $b->path));

        $documents = [];
        $downloaded = 0;
        $limited = false;
        foreach ($items as $item) {
            $relative = ltrim(substr(self::normalizePath($item->path), strlen($prefix)), '/');
            if ($known($item->itemId)) {
                $documents[] = $this->parse($item->itemId, $relative, '', $item->modifiedAt);

                continue;
            }
            if ($downloaded >= $limit) {
                $limited = true;

                continue;
            }
            $content = (string) $source->intakeDownload($connection, $item)->getContents();
            $documents[] = $this->parse($item->itemId, $relative, $content, $item->modifiedAt, (string) ($connection->name ?? ''));
            $downloaded++;
        }

        return ['documents' => $documents, 'limited' => $limited];
    }

    /** Eine Markdown-Datei als Dokument; `$relativePath` ist der Pfad im Tresor. */
    public function parse(string $externalId, string $relativePath, string $content, ?string $modifiedAt = null, string $connectionLabel = ''): ImportedDocument {
        $content = str_replace(["\r\n", "\r"], "\n", preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content);
        [$frontMatter, $body] = $this->splitFrontMatter($content);

        $segments = array_values(array_filter(explode('/', $relativePath), static fn (string $s): bool => $s !== ''));
        $file = (string) array_pop($segments);
        $basename = preg_replace('/\.md$/i', '', $file) ?? $file;
        $title = is_string($frontMatter['title'] ?? null) && trim($frontMatter['title']) !== '' ? trim($frontMatter['title']) : $basename;

        $pathName = implode('/', [...$segments, $basename]);
        $aliases = array_filter((array) ($frontMatter['aliases'] ?? $frontMatter['alias'] ?? []), 'is_string');

        return new ImportedDocument(
            externalId: $externalId,
            title: $title,
            text: trim($body),
            folders: $segments,
            tags: $this->tags($frontMatter['tags'] ?? $frontMatter['tag'] ?? null, $body),
            names: array_values(array_unique([$basename, $pathName, ...$aliases])),
            linkNames: $this->wikilinks($body),
            modifiedAt: $modifiedAt !== null ? CarbonImmutable::parse($modifiedAt) : null,
            sourceLabel: trim('Obsidian · ' . ($connectionLabel !== '' ? $connectionLabel . ' · ' : '') . $relativePath),
        );
    }

    private function belongsToVault(IntakeItem $item, string $prefix): bool {
        $path = self::normalizePath($item->path);
        if (! str_ends_with(mb_strtolower($path), '.md') || $item->size > self::MAX_FILE_BYTES) {
            return false;
        }
        if ($prefix !== '' && ! str_starts_with($path, $prefix . '/')) {
            return false;
        }
        $relative = $prefix === '' ? $path : substr($path, strlen($prefix) + 1);

        return preg_match('#(^|/)\.(obsidian|trash)(/|$)#', $relative) !== 1;
    }

    /** @return array{0: array<mixed>, 1: string} */
    private function splitFrontMatter(string $content): array {
        if (preg_match('/\A---\n(.*?)\n(?:---|\.\.\.)\n?(.*)\z/s', $content, $match) !== 1) {
            return [[], $content];
        }

        try {
            $parsed = Yaml::parse($match[1]);
        } catch (ParseException) {
            // Kaputter Kopf: Text trotzdem übernehmen, ohne Kopfzeilen.
            $parsed = [];
        }

        return [is_array($parsed) ? $parsed : [], $match[2]];
    }

    /**
     * Schlagwörter aus dem Kopf (Liste oder Text) und `#schlagwort` im Text —
     * außerhalb von Code-Blöcken, ohne Überschriften und reine Zahlen.
     *
     * @return list<string>
     */
    private function tags(mixed $frontMatterTags, string $body): array {
        $tags = [];
        $values = is_array($frontMatterTags) ? $frontMatterTags : preg_split('/[\s,]+/', (string) $frontMatterTags);
        foreach ($values ?: [] as $value) {
            if (is_string($value) || is_int($value)) {
                $tags[] = ltrim(trim((string) $value), '#');
            }
        }

        $text = preg_replace('/```.*?```|`[^`\n]*`/s', ' ', $body) ?? $body;
        if (preg_match_all('/(?<![\p{L}\p{N}_\/&#])#([\p{L}\p{N}_\/-]*\p{L}[\p{L}\p{N}_\/-]*)/u', $text, $matches) > 0) {
            array_push($tags, ...$matches[1]);
        }

        $unique = [];
        foreach ($tags as $tag) {
            // Verschachtelte Obsidian-Schlagwörter (projekt/kunde) bleiben ein Schlagwort; Kommas trennen im Tag-Feld.
            $tag = str_replace(',', ' ', trim($tag));
            if ($tag !== '' && mb_strlen($tag) <= 50) {
                $unique[mb_strtolower($tag)] ??= $tag;
            }
        }

        return array_values($unique);
    }

    /**
     * `[[Ziel]]`, `[[Ziel|Anzeige]]`, `[[Ziel#Abschnitt]]` — Einbettungen
     * (`![[Bild.png]]`) sind keine Verweise.
     *
     * @return list<string>
     */
    private function wikilinks(string $body): array {
        if (preg_match_all('/(?<!!)\[\[([^\]\|#\^]+)(?:[#\^][^\]\|]*)?(?:\|[^\]]*)?\]\]/u', $body, $matches) === 0) {
            return [];
        }

        $names = [];
        foreach ($matches[1] as $target) {
            $name = preg_replace('/\.md$/i', '', trim($target)) ?? trim($target);
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    private static function normalizePath(string $path): string {
        return trim(str_replace('\\', '/', trim($path)), '/');
    }
}
