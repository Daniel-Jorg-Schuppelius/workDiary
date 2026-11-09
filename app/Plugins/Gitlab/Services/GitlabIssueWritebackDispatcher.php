<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GitlabIssueWritebackDispatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Gitlab\Services;

use App\Models\Integration\{ExternalReference, IntegrationOutboxEntry};
use App\Models\Project\Task;
use App\Models\Time\TimeEntry;
use App\Plugins\Gitlab\Api\{GitlabClient, GitlabClientFactory};
use App\Plugins\Gitlab\{GitlabConfig, GitlabPlugin};
use App\Plugins\Support\GitIssueImport\AbstractGitIssueWritebackDispatcher;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Rückrichtung nach GitLab: Status (Audit 2026-08, Welle 1.4: erledigte
 * Aufgabe → Issue schließen + Notiz, wiedereröffnete → öffnen) und gebuchte
 * Zeit (MVP-1513, `add_spent_time`). external_id-Format des Importers:
 * `projectId#iid`.
 */
class GitlabIssueWritebackDispatcher extends AbstractGitIssueWritebackDispatcher {
    public const string OP_SPENT_TIME = GitlabPlugin::ID . '.issue.spent_time';

    /** ExternalReference-Typ der gebuchten Zeiten: ein Satz je Zeiteintrag. */
    public const string EXT_TYPE_SPENT_TIME = 'spent_time';

    public function pluginId(): string {
        return GitlabPlugin::ID;
    }

    public function writebackEnabled(int $organizationId): bool {
        $config = GitlabConfig::resolve($organizationId);

        return $config['enabled'] && $config['writeback'] && GitlabConfig::isConfigured($organizationId);
    }

    public function externalType(): string {
        return GitlabPlugin::EXT_TYPE_ISSUE;
    }

    public function dispatch(IntegrationOutboxEntry $entry): bool {
        if ($entry->operation === self::OP_SPENT_TIME) {
            return $this->dispatchSpentTime($entry);
        }

        return parent::dispatch($entry);
    }

    /** Bereits als Zeitaufwand an GitLab gebucht? */
    public static function alreadyBooked(int $organizationId, TimeEntry $timeEntry): bool {
        return ExternalReference::query()
            ->forPlugin($organizationId, GitlabPlugin::ID, self::EXT_TYPE_SPENT_TIME)
            ->forReferenceable($timeEntry)
            ->exists();
    }

    /**
     * Gebucht wird der Stand beim Zustellen, je Eintrag genau einmal. Die API
     * liefert keine Kennung der Buchung und dokumentiert keine negativen
     * Dauern; Korrekturen und Löschungen lassen sich daher nicht gezielt
     * nachziehen, und `reset_spent_time` löschte die Zeit aller Beteiligten.
     */
    private function dispatchSpentTime(IntegrationOutboxEntry $entry): bool {
        $organizationId = (int) $entry->organization_id;
        if (! GitlabConfig::timeWritebackEnabled($organizationId)) {
            return true;
        }

        $timeEntry = TimeEntry::query()
            ->withoutGlobalScopes()
            ->whereKey((int) ($entry->payload['time_entry_id'] ?? 0))
            ->where('organization_id', $organizationId)
            ->first();
        if ($timeEntry === null || $timeEntry->task_id === null || (int) $timeEntry->minutes <= 0 || self::alreadyBooked($organizationId, $timeEntry)) {
            return true;
        }

        $issue = ExternalReference::query()
            ->forPlugin($organizationId, GitlabPlugin::ID, GitlabPlugin::EXT_TYPE_ISSUE)
            ->where('referenceable_type', (new Task)->getMorphClass())
            ->where('referenceable_id', $timeEntry->task_id)
            ->first();
        if ($issue === null) {
            return true;
        }

        [$projectId, $iid] = $this->parse((string) $issue->external_id);
        $minutes = (int) $timeEntry->minutes;
        $summary = trim((string) $timeEntry->description);

        try {
            $stats = $this->client($organizationId)->addSpentTime($projectId, $iid, $minutes, $summary !== '' ? Str::limit($summary, 250, '…') : null);
        } catch (ConnectException $e) {
            // Ob die Buchung ankam, ist offen; eine Wiederholung könnte doppelt buchen.
            Log::warning('GitLab-Zeitbuchung: Transportfehler, keine Wiederholung.', [
                'organization_id' => $organizationId,
                'time_entry_id' => $timeEntry->getKey(),
                'error' => $e->getMessage(),
            ]);

            return true;
        }

        ExternalReference::query()->withoutGlobalScopes()->create([
            'organization_id' => $organizationId,
            'plugin_id' => GitlabPlugin::ID,
            'external_type' => self::EXT_TYPE_SPENT_TIME,
            'referenceable_type' => $timeEntry->getMorphClass(),
            'referenceable_id' => $timeEntry->getKey(),
            'external_id' => $issue->external_id . ':' . $timeEntry->getKey(),
            'payload' => [
                'issue' => (string) $issue->external_id,
                'minutes' => $minutes,
                'total_time_spent' => isset($stats['total_time_spent']) ? (int) $stats['total_time_spent'] : null,
            ],
            'synced_at' => now(),
        ]);

        return true;
    }

    protected function applyState(int $organizationId, string $externalId, bool $closed): void {
        [$projectId, $iid] = $this->parse($externalId);
        $this->client($organizationId)->setIssueState($projectId, $iid, $closed);
    }

    protected function comment(int $organizationId, string $externalId, string $body): void {
        [$projectId, $iid] = $this->parse($externalId);
        $this->client($organizationId)->commentIssue($projectId, $iid, $body);
    }

    private function client(int $organizationId): GitlabClient {
        return app(GitlabClientFactory::class)->for($organizationId);
    }

    /** @return array{0: string, 1: int} */
    private function parse(string $externalId): array {
        // `projectId#iid` — Bestandsformat des Importers (nie ändern).
        $pos = strrpos($externalId, '#');
        if ($pos === false || $pos === 0) {
            throw new \RuntimeException('GitLab-Referenz nicht auflösbar: ' . $externalId);
        }

        return [substr($externalId, 0, $pos), (int) substr($externalId, $pos + 1)];
    }
}
