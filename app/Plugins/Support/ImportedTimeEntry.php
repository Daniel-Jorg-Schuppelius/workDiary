<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportedTimeEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

use Carbon\CarbonImmutable;
use CommonToolkit\Enums\HashAlgorithm;
use CommonToolkit\Helper\Data\CryptoHelper;

/**
 * Normalisierte Repräsentation eines extern erfassten Zeiteintrags
 * (Kimai/Clockify/…, CSV-Export oder REST-API) für die
 * {@see MatchingTimeImportService}-Pipeline. `activity` trägt bei Kimai die
 * Tätigkeit, bei Clockify den Task. CSV-Zeilen haben keine stabile ID — der
 * Idempotenz-Schlüssel wird deterministisch gehasht; API-Einträge nutzen die
 * stabile Fremd-ID. Die numerischen Fremd-IDs (client/project/activity) kommen
 * nur über APIs mit und tragen ggf. das Export-Mapping des Plugins.
 */
final class ImportedTimeEntry {
    public const SOURCE_CSV = 'csv';

    public const SOURCE_API = 'api';

    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public readonly string $entryKey,
        public readonly ?string $clientName,
        public readonly ?string $projectName,
        public readonly ?string $activity,
        public readonly ?string $description,
        public readonly CarbonImmutable $startedAt,
        public readonly CarbonImmutable $endedAt,
        public readonly bool $billable = false,
        public readonly ?string $userEmail = null,
        public readonly array $tags = [],
        public readonly string $source = self::SOURCE_CSV,
        public readonly ?int $clientId = null,
        public readonly ?int $projectId = null,
        public readonly ?int $activityId = null,
        /** Quell-Workspace (nur Toggl-API) — trennt Inbox-Gruppen je Workspace. */
        public readonly ?int $workspaceId = null,
        public readonly ?string $workspaceName = null,
    ) {}

    /** Verbindungsdauer in Minuten (mind. 1, falls > 0 Sekunden). */
    public function minutes(): int {
        $seconds = (int) $this->startedAt->diffInSeconds($this->endedAt, absolute: true);

        return $seconds <= 0 ? 0 : max(1, (int) round($seconds / 60));
    }

    /**
     * Idempotenz-Schlüssel eines CSV-Eintrags. Activity und E-Mail fließen mit
     * ein, damit zwei Zeilen mit gleichem Projekt, aber verschiedener Tätigkeit/
     * Person nicht kollidieren.
     */
    public static function csvKey(string $start, string $end, ?string $client, ?string $project, ?string $activity, ?string $description, ?string $email): string {
        return 'csv:' . CryptoHelper::hash(
            implode('|', [$start, $end, (string) $client, (string) $project, (string) $activity, (string) $description, (string) $email]),
            HashAlgorithm::SHA1,
        );
    }

    /** Idempotenz-Schlüssel eines API-Eintrags (stabile Fremd-ID). */
    public static function apiKey(int|string $externalId): string {
        return 'api:' . $externalId;
    }
}
