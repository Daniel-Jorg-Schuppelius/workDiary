<?php
/*
 * Created on   : Mon Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenderNoticeImporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Tenders;

use App\Models\Tenders\TenderNotice;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use Carbon\CarbonInterface;
use CommonToolkit\Helper\FileSystem\FileTypes\ZipFile;
use Exception;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use RuntimeException;

/**
 * Holt die Bekanntmachungen des Bundes und legt sie ab (MVP-629).
 *
 * Der Bekanntmachungsservice liefert unter
 * `GET /api/notice-exports?pubDay=YYYY-MM-DD&format=ocds.zip` alle an einem Tag
 * veröffentlichten Fassungen als ZIP — **registrierungsfrei und unter CC0**.
 * Deshalb wird ausschließlich diese offene Quelle genutzt und kein Portal
 * gescrapt.
 *
 * Zwei Eigenheiten der Quelle bestimmen den Ablauf:
 *
 * 1. **Ein Tag ist erst am Folgetag vollständig** — die API gibt an, dass nur
 *    bis Mitternacht des Vortags verarbeitete Bekanntmachungen enthalten sind.
 *    Der Abruf holt deshalb standardmäßig gestern, nicht heute.
 * 2. **Bekanntmachungen werden berichtigt.** Notice-ID und Version zusammen
 *    identifizieren eine Fassung; eine erneute Fassung ist ein neuer Datensatz,
 *    keine Änderung des alten.
 */
final class TenderNoticeImporter {
    private const BASE_URL = 'https://oeffentlichevergabe.de';
    private const PATH = '/api/notice-exports';

    /** Mehr Einzeldateien enthält kein plausibles Tagespaket (C6-Guard). */
    private const MAX_ENTRIES = 20_000;

    /** Obergrenze der entpackten Gesamtgröße — wird VOR dem Entpacken geprüft. */
    private const MAX_UNCOMPRESSED_BYTES = 256 * 1024 * 1024;

    private readonly PluginApiClient $client;

    public function __construct(PluginHttpFactory $http) {
        // Über die Fabrik, damit Tests den Transport ersetzen können - der
        // Abruf soll nicht am Netz hängen.
        $this->client = $http->client('tender-notices', self::BASE_URL);
    }

    /**
     * Holt einen Veröffentlichungstag und legt neue Fassungen ab.
     *
     * Die IDs der neu abgelegten Fassungen kommen mit zurück: Nur sie sind
     * abzugleichen, und ein Datumsvergleich träfe die falschen — das
     * Veröffentlichungsdatum einer Fassung ist nicht der Abruftag.
     *
     * @return array{fetched: int, stored: int, day: string, ids: list<int>}
     */
    public function importDay(?CarbonInterface $day = null): array {
        // Gestern ist der jüngste vollständige Tag - heute wäre lückenhaft.
        $target = $day?->copy() ?? Carbon::yesterday();
        $pubDay = $target->format('Y-m-d');

        $zip = $this->download($pubDay);
        $documents = $this->extract($zip);

        $ids = [];
        foreach ($documents as $document) {
            $notice = $this->store($document, $target);
            if ($notice !== null) {
                $ids[] = (int) $notice->id;
            }
        }

        return ['fetched' => count($documents), 'stored' => count($ids), 'day' => $pubDay, 'ids' => $ids];
    }

    /** Lädt das Tages-ZIP. Ein leeres ZIP ist ein gültiges Ergebnis. */
    private function download(string $pubDay): string {
        $response = $this->client->getResponse(self::PATH, [
            'pubDay' => $pubDay,
            'format' => 'ocds.zip',
        ], ['timeout' => 120]);

        if ($response->failed()) {
            throw new RuntimeException("Bekanntmachungen für {$pubDay} nicht abrufbar (HTTP {$response->status()}).");
        }

        return $response->body();
    }

    /**
     * Entpackt das ZIP und gibt die enthaltenen OCDS-Dokumente zurück.
     *
     * @return list<array<string, mixed>>
     */
    private function extract(string $zip): array {
        if (trim($zip) === '') {
            return [];
        }

        // C6 (Vollscan 2026-08-23): In-Memory-Lesen über das Common-Toolkit —
        // harter Zip-Slip-Guard und Größen-Limits inklusive.
        try {
            $entries = ZipFile::readEntries($zip, self::MAX_ENTRIES, self::MAX_UNCOMPRESSED_BYTES);
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException('Das Tagespaket verletzt die Archiv-Grenzen: ' . $e->getMessage(), previous: $e);
        } catch (Exception $e) {
            throw new RuntimeException('Das Tagespaket ließ sich nicht öffnen.', previous: $e);
        }

        $documents = [];
        foreach ($entries as $name => $content) {
            if (!str_ends_with(strtolower($name), '.json')) {
                continue;
            }
            $decoded = json_decode($content, true);
            if (!is_array($decoded)) {
                continue;
            }
            // Ein Paket kann eine Release-Sammlung oder ein einzelnes Release
            // sein - beides kommt vor.
            foreach ($this->releasesOf($decoded) as $release) {
                $documents[] = $release;
            }
        }

        return $documents;
    }

    /**
     * @param  array<string, mixed> $decoded
     * @return list<array<string, mixed>>
     */
    private function releasesOf(array $decoded): array {
        if (isset($decoded['releases']) && is_array($decoded['releases'])) {
            return array_values(array_filter($decoded['releases'], is_array(...)));
        }

        return [$decoded];
    }

    /**
     * Legt eine Fassung ab. Gibt `null` zurück, wenn sie bereits bekannt ist —
     * dieselbe Notice-ID in derselben Version ist dieselbe Bekanntmachung.
     *
     * @param array<string, mixed> $release
     */
    private function store(array $release, CarbonInterface $day): ?TenderNotice {
        $noticeId = (string) ($release['id'] ?? $release['ocid'] ?? '');
        if ($noticeId === '') {
            return null;
        }

        $tender = is_array($release['tender'] ?? null) ? $release['tender'] : [];
        $version = (string) ($release['tag'][0] ?? $tender['statusDetails'] ?? '1');

        $existing = TenderNotice::query()
            ->where('notice_id', $noticeId)
            ->where('version', $version)
            ->exists();
        if ($existing) {
            return null;
        }

        return TenderNotice::query()->create([
            'notice_id' => $noticeId,
            'version' => mb_substr($version, 0, 20),
            'ocid' => isset($release['ocid']) ? mb_substr((string) $release['ocid'], 0, 120) : null,
            'title' => mb_substr((string) ($tender['title'] ?? $release['title'] ?? '—'), 0, 500),
            'summary' => isset($tender['description']) ? (string) $tender['description'] : null,
            'buyer_name' => isset($release['buyer']['name']) ? mb_substr((string) $release['buyer']['name'], 0, 300) : null,
            'procedure_method' => isset($tender['procurementMethod']) ? mb_substr((string) $tender['procurementMethod'], 0, 60) : null,
            'cpv_codes' => $this->cpvCodes($tender),
            'nuts_code' => $this->nutsCode($tender),
            'estimated_value' => isset($tender['value']['amount']) ? (float) $tender['value']['amount'] : null,
            'currency' => isset($tender['value']['currency']) ? mb_substr((string) $tender['value']['currency'], 0, 3) : null,
            'published_on' => isset($release['date']) ? Carbon::parse((string) $release['date'])->toDateString() : $day->toDateString(),
            'submission_deadline' => isset($tender['tenderPeriod']['endDate'])
                ? Carbon::parse((string) $tender['tenderPeriod']['endDate'])
                : null,
            'url' => isset($release['url']) ? (string) $release['url'] : null,
            'payload' => $release,
        ]);
    }

    /**
     * CPV-Codes stehen als Klassifikation an der Ausschreibung und an ihren
     * Positionen — beide zählen, sonst entgeht ein Los.
     *
     * @param  array<string, mixed> $tender
     * @return list<string>
     */
    private function cpvCodes(array $tender): array {
        $codes = [];

        if (isset($tender['classification']['id'])) {
            $codes[] = (string) $tender['classification']['id'];
        }
        foreach ((array) ($tender['items'] ?? []) as $item) {
            if (is_array($item) && isset($item['classification']['id'])) {
                $codes[] = (string) $item['classification']['id'];
            }
            foreach ((array) ($item['additionalClassifications'] ?? []) as $additional) {
                if (is_array($additional) && isset($additional['id'])) {
                    $codes[] = (string) $additional['id'];
                }
            }
        }

        return array_values(array_unique($codes));
    }

    /** @param array<string, mixed> $tender */
    private function nutsCode(array $tender): ?string {
        foreach ((array) ($tender['items'] ?? []) as $item) {
            $region = $item['deliveryAddresses'][0]['region'] ?? $item['deliveryLocation']['region'] ?? null;
            if (is_string($region) && $region !== '') {
                return mb_substr($region, 0, 10);
            }
        }

        return null;
    }
}
