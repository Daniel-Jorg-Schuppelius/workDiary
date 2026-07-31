<?php
/*
 * Created on   : Fri Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SidebarNewsFeedService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\UI;

use App\Support\{Setting, UrlSafety};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\{StringHelper, XmlHelper};
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\{Cache, Http};
use RuntimeException;
use SimpleXMLElement;
use Throwable;

/**
 * Holt einen optionalen RSS-2.0-/Atom-Feed für die eingeklappte Hilfe-Rail.
 *
 * Seitenaufrufe lesen ausschließlich den letzten erfolgreichen Cache-Stand.
 * Der externe Abruf läuft über den Scheduler/CLI, damit ein langsamer oder
 * ausgefallener Anbieter niemals die App-Shell verzögert. Feed-Markup bleibt
 * untrusted: gespeichert werden nur normalisierter Text, öffentliche URLs und
 * ISO-Zeitstempel.
 */
final class SidebarNewsFeedService {
    public const CACHE_KEY = 'ui.news_feed.payload';

    private const MAX_BODY_BYTES = 524_288;

    /** @return list<array{title: string, url: string, source: string, published_at: string|null}> */
    public function items(): array {
        if (! $this->isEnabled()) {
            return [];
        }

        $url = $this->configuredUrl();
        $payload = Cache::get(self::CACHE_KEY);
        if (! is_array($payload) || ($payload['url_hash'] ?? null) !== hash('sha256', $url)) {
            return [];
        }

        $items = $payload['items'] ?? null;
        if (! is_array($items)) {
            return [];
        }

        $valid = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $title = $item['title'] ?? null;
            $itemUrl = $item['url'] ?? null;
            $source = $item['source'] ?? null;
            $publishedAt = $item['published_at'] ?? null;
            if (! is_string($title) || $title === '' || ! is_string($itemUrl) || ! is_string($source)) {
                continue;
            }
            if (! UrlSafety::isAcceptableExternalHttpUrl($itemUrl)) {
                continue;
            }
            if ($publishedAt !== null && ! is_string($publishedAt)) {
                $publishedAt = null;
            }

            $valid[] = [
                'title' => $title,
                'url' => $itemUrl,
                'source' => $source,
                'published_at' => $publishedAt,
            ];
        }

        return array_slice($valid, 0, $this->maxItems());
    }

    public function isEnabled(): bool {
        return (bool) Setting::get('ui.news_feed.enabled', false)
            && $this->configuredUrl() !== '';
    }

    public function rotationIntervalMilliseconds(): int {
        return max(5, (int) config('ui.news_feed.rotation_seconds', 15)) * 1000;
    }

    /**
     * Aktualisiert den Cache atomar erst nach einem vollständig erfolgreichen
     * Abruf. Bei Fehlern bleibt der letzte gültige Stand erhalten.
     */
    public function refresh(): int {
        if (! $this->isEnabled()) {
            return 0;
        }

        $url = $this->configuredUrl();
        if (! UrlSafety::isPubliclyRoutableHttpUrl($url)) {
            throw new RuntimeException('Die Neuigkeiten-Feed-URL ist nicht öffentlich erreichbar.');
        }

        $response = Http::withoutRedirecting()
            ->connectTimeout(3)
            ->timeout(8)
            ->withHeaders([
                'Accept' => 'application/atom+xml, application/rss+xml, application/xml, text/xml;q=0.9',
                'User-Agent' => 'WorkDiary-NewsFeed/1.0',
            ])
            ->get($url);

        $this->assertUsableResponse($response);
        $parsed = $this->parse($response->body());
        if ($parsed['items'] === []) {
            throw new RuntimeException('Der Neuigkeiten-Feed enthält keine verwendbaren Beiträge.');
        }

        Cache::forever(self::CACHE_KEY, [
            'url_hash' => hash('sha256', $url),
            'refreshed_at' => CarbonImmutable::now()->toIso8601String(),
            'items' => array_slice($parsed['items'], 0, $this->maxItems()),
        ]);

        return min(count($parsed['items']), $this->maxItems());
    }

    private function configuredUrl(): string {
        return trim((string) Setting::get('ui.news_feed.url', ''));
    }

    private function maxItems(): int {
        return max(1, min(10, (int) config('ui.news_feed.max_items', 5)));
    }

    private function assertUsableResponse(Response $response): void {
        if (! $response->successful()) {
            throw new RuntimeException('Neuigkeiten-Feed konnte nicht geladen werden (HTTP ' . $response->status() . ').');
        }

        $declaredLength = (int) $response->header('Content-Length');
        if ($declaredLength > self::MAX_BODY_BYTES || strlen($response->body()) > self::MAX_BODY_BYTES) {
            throw new RuntimeException('Der Neuigkeiten-Feed überschreitet die erlaubte Größe.');
        }
    }

    /**
     * @return array{source: string, items: list<array{title: string, url: string, source: string, published_at: string|null}>}
     */
    private function parse(string $body): array {
        $xml = XmlHelper::safeLoadString($body, LIBXML_NOCDATA | LIBXML_COMPACT);
        if (! $xml instanceof SimpleXMLElement) {
            throw new RuntimeException('Der Neuigkeiten-Feed ist kein gültiges XML-Dokument.');
        }

        return match (strtolower($xml->getName())) {
            'rss' => $this->parseRss($xml),
            'feed' => $this->parseAtom($xml),
            default => throw new RuntimeException('Der Neuigkeiten-Feed ist weder RSS 2.0 noch Atom.'),
        };
    }

    /**
     * @return array{source: string, items: list<array{title: string, url: string, source: string, published_at: string|null}>}
     */
    private function parseRss(SimpleXMLElement $rss): array {
        $channel = $rss->channel;
        $source = $this->plainText((string) ($channel->title ?? '')) ?: 'RSS';
        $items = [];

        foreach ($channel->item ?? [] as $item) {
            $normalized = $this->normalizeItem(
                (string) ($item->title ?? ''),
                (string) ($item->link ?? ''),
                $source,
                (string) ($item->pubDate ?? ''),
            );
            if ($normalized !== null) {
                $items[] = $normalized;
            }
        }

        return ['source' => $source, 'items' => $items];
    }

    /**
     * @return array{source: string, items: list<array{title: string, url: string, source: string, published_at: string|null}>}
     */
    private function parseAtom(SimpleXMLElement $atom): array {
        $defaultNamespace = $atom->getNamespaces(true)[''] ?? null;
        $feed = $defaultNamespace !== null ? $atom->children($defaultNamespace) : $atom;
        $source = $this->plainText((string) ($feed->title ?? '')) ?: 'Atom';
        $items = [];

        foreach ($feed->entry ?? [] as $entry) {
            $fields = $defaultNamespace !== null ? $entry->children($defaultNamespace) : $entry;
            $url = '';
            foreach ($fields->link ?? [] as $link) {
                $attributes = $link->attributes();
                $rel = strtolower((string) ($attributes['rel'] ?? 'alternate'));
                $href = trim((string) ($attributes['href'] ?? ''));
                if ($href !== '' && in_array($rel, ['', 'alternate'], true)) {
                    $url = $href;
                    break;
                }
            }

            $normalized = $this->normalizeItem(
                (string) ($fields->title ?? ''),
                $url,
                $source,
                (string) ($fields->published ?? $fields->updated ?? ''),
            );
            if ($normalized !== null) {
                $items[] = $normalized;
            }
        }

        return ['source' => $source, 'items' => $items];
    }

    /** @return array{title: string, url: string, source: string, published_at: string|null}|null */
    private function normalizeItem(string $title, string $url, string $source, string $publishedAt): ?array {
        $title = mb_substr($this->plainText($title), 0, 240);
        $url = trim($url);
        if ($title === '' || ! UrlSafety::isAcceptableExternalHttpUrl($url)) {
            return null;
        }

        $published = null;
        if (trim($publishedAt) !== '') {
            try {
                $published = CarbonImmutable::parse($publishedAt)->toIso8601String();
            } catch (Throwable) {
                // Ein kaputtes optionales Datum darf den Beitrag nicht verwerfen.
            }
        }

        return [
            'title' => $title,
            'url' => $url,
            'source' => mb_substr($source, 0, 100),
            'published_at' => $published,
        ];
    }

    private function plainText(string $value): string {
        return StringHelper::stripHtml(StringHelper::htmlEntitiesToText($value));
    }
}
