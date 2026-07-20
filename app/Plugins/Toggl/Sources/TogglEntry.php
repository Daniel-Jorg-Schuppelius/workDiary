<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Toggl\Sources;

use Carbon\CarbonImmutable;
use CommonToolkit\Enums\HashAlgorithm;
use CommonToolkit\Helper\Data\CryptoHelper;

/**
 * Normalisierte Repräsentation eines einzelnen Toggl-Zeiteintrags, unabhängig
 * von der Quelle (API oder CSV-Export). {@see TogglApiClient} und
 * {@see TogglCsvParser} mappen ihre Rohdaten auf dieses DTO; der
 * {@see \App\Plugins\Toggl\TogglImportService} verarbeitet ausschließlich diese Struktur.
 */
final class TogglEntry {
    public const SOURCE_API = 'api';

    public const SOURCE_CSV = 'csv';

    public function __construct(
        /** Quelle: {@see self::SOURCE_API} | {@see self::SOURCE_CSV}. */
        public readonly string $source,
        /** Stabiler Idempotenz-Schlüssel ("toggl:<id>" bzw. "csv:<hash>"). */
        public readonly string $entryKey,
        /** Toggl-Client-Name (→ Kunde). Leer/null = ohne Client. */
        public readonly ?string $clientName,
        /** Toggl-Projekt-Name (→ Projekt). Leer/null = ohne Projekt. */
        public readonly ?string $projectName,
        public readonly ?string $description,
        public readonly CarbonImmutable $startedAt,
        public readonly CarbonImmutable $endedAt,
        public readonly bool $billable = false,
        /** Toggl-User-E-Mail (informativ, für die Inbox-Anzeige). */
        public readonly ?string $userEmail = null,
        /** Stabile Toggl-Client-ID (nur API; CSV liefert keine) → bevorzugter Kunden-Schlüssel. */
        public readonly ?int $clientId = null,
        /** Stabile Toggl-Projekt-ID (nur API; CSV liefert keine) → bevorzugter Projekt-Schlüssel. */
        public readonly ?int $projectId = null,
        /** Toggl-Workspace-ID (nur API) — trennt Inbox-Gruppen je Workspace. */
        public readonly ?int $workspaceId = null,
    ) {}

    /** Verbindungsdauer in Minuten (mind. 1, falls > 0 Sekunden). */
    public function minutes(): int {
        $seconds = (int) $this->startedAt->diffInSeconds($this->endedAt, absolute: true);

        return $seconds <= 0 ? 0 : max(1, (int) round($seconds / 60));
    }

    /** Baut den Idempotenz-Schlüssel für einen CSV-Eintrag (keine Toggl-ID vorhanden). */
    public static function csvKey(string $start, string $end, ?string $client, ?string $project, ?string $description): string {
        return 'csv:' . CryptoHelper::hash(implode('|', [$start, $end, (string) $client, (string) $project, (string) $description]), HashAlgorithm::SHA1);
    }
}
