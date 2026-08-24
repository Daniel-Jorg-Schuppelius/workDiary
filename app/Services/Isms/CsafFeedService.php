<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CsafFeedService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Isms;

use App\Enums\Isms\AdvisoryFormat;
use App\Models\{Organization, User};
use App\Plugins\Support\PluginHttpFactory;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * CSAF-Feed-Pull (Nachtrag 044b, AR §21): lädt Plattform-Advisories eines
 * CSAF-Trusted-Providers über dessen provider-metadata.json. Unterstützt
 * beide Verteilungswege — ROLIE-Feeds (BSI WID) UND verzeichnisbasiert mit
 * directory_url + changes.csv (Red Hat/SUSE). Die Dokumente laufen durch den
 * bestehenden {@see AdvisoryImportService} (idempotent per Datei-Hash,
 * VEX-Regeln inklusive); ein fehlerhaftes Dokument bricht den Pull nicht ab.
 *
 * Default-Provider: BSI WID (CERT-Bund).
 */
class CsafFeedService {
    public const DEFAULT_PROVIDER = 'https://wid.cert-bund.de/.well-known/csaf/provider-metadata.json';

    /** Obergrenze je Pull — die neuesten Dokumente zuerst. */
    public const DEFAULT_LIMIT = 20;

    public function __construct(
        private readonly AdvisoryImportService $importer,
        private readonly PluginHttpFactory $http,
    ) {}

    /**
     * @return array{documents: int, imported: int, skipped: int, errors: int}
     */
    public function pull(string $providerMetadataUrl, Organization $organization, User $importer, int $limit = self::DEFAULT_LIMIT): array {
        $metadata = $this->fetchJson($providerMetadataUrl);
        if ($metadata === null) {
            throw new RuntimeException('provider-metadata.json nicht erreichbar oder ungültig.');
        }

        $documentUrls = array_slice($this->documentUrls($metadata), 0, $limit);

        $imported = 0;
        $skipped = 0;
        $errors = 0;
        foreach ($documentUrls as $url) {
            $response = $this->http->coreClient('csaf', $url)->getResponse($url, [], ['timeout' => 30]);
            if ($response->failed()) {
                $errors++;

                continue;
            }

            $content = $response->body();
            $format = str_contains($content, '"csaf_vex"') ? AdvisoryFormat::Vex : AdvisoryFormat::Csaf;

            try {
                $advisory = $this->importer->importCsaf($content, $organization, $importer, $format);
                $advisory->wasRecentlyCreated ? $imported++ : $skipped++;
            } catch (ValidationException) {
                $errors++;
            } catch (Throwable) {
                $errors++;
            }
        }

        return [
            'documents' => count($documentUrls),
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Dokument-URLs aus den Verteilungen der provider-metadata: ROLIE-Feeds
     * (entries[].content.src, neueste zuerst) und directory_url/changes.csv.
     *
     * @param array<string, mixed> $metadata
     * @return list<string>
     */
    private function documentUrls(array $metadata): array {
        $urls = [];

        foreach ((array) ($metadata['distributions'] ?? []) as $distribution) {
            if (! is_array($distribution)) {
                continue;
            }

            // Weg 1: ROLIE-Feeds (z. B. BSI WID).
            foreach ((array) data_get($distribution, 'rolie.feeds', []) as $feed) {
                $feedUrl = is_array($feed) ? (string) ($feed['url'] ?? '') : '';
                if ($feedUrl === '') {
                    continue;
                }
                $feedDoc = $this->fetchJson($feedUrl);
                /** @var list<array<string, mixed>> $entries */
                $entries = (array) data_get($feedDoc, 'feed.entry', []);
                usort($entries, static fn(array $a, array $b): int => strcmp((string) ($b['updated'] ?? ''), (string) ($a['updated'] ?? '')));
                foreach ($entries as $entry) {
                    $src = (string) data_get($entry, 'content.src', '');
                    if ($src !== '') {
                        $urls[] = $src;
                    }
                }
            }

            // Weg 2: verzeichnisbasiert (directory_url + changes.csv).
            $directoryUrl = rtrim((string) ($distribution['directory_url'] ?? ''), '/');
            if ($directoryUrl !== '') {
                $changes = $this->http->coreClient('csaf', $directoryUrl)
                    ->getResponse($directoryUrl . '/changes.csv', [], ['timeout' => 30]);
                if ($changes->successful()) {
                    // Format: "pfad/dokument.json","2026-07-01T00:00:00Z" — neueste
                    // zuerst; Toolkit-Parser statt str_getcsv je Zeile (C5).
                    $rows = \App\Support\Toolkit\CsvFacade::parseRows($changes->body(), ',');
                    usort($rows, static fn (array $a, array $b): int => strcmp((string) ($b[1] ?? ''), (string) ($a[1] ?? '')));
                    foreach ($rows as $row) {
                        $file = trim((string) ($row[0] ?? ''));
                        if ($file !== '') {
                            $urls[] = $directoryUrl . '/' . ltrim($file, '/');
                        }
                    }
                }
            }
        }

        return array_values(array_unique($urls));
    }

    /** @return array<string, mixed>|null */
    private function fetchJson(string $url): ?array {
        $response = $this->http->coreClient('csaf', $url)->getResponse($url, [], ['timeout' => 30]);
        if ($response->failed()) {
            return null;
        }

        $decoded = json_decode($response->body(), true);

        return is_array($decoded) ? $decoded : null;
    }
}
