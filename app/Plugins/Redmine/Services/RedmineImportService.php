<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RedmineImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Redmine\Services;

use App\Enums\Task\TaskStatus;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\Organization;
use App\Plugins\Redmine\Api\{RedmineClient, RedmineClientFactory};
use App\Plugins\Redmine\Models\{RedmineConnection, RedmineProjectLink};
use App\Plugins\Redmine\RedminePlugin;
use App\Plugins\Support\TaskSync\{AbstractTaskImportService, IncompleteRemoteReadException, RemoteTask, SelfHostedInstance};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\DateHelper;
use Illuminate\Database\Eloquent\{Collection as EloquentCollection, Model};
use Throwable;

/**
 * Redmine-Adapter des Aufgabenimports (MVP-1322): Issues je zugeordnetem
 * Projekt ohne Unterprojekte — Erstlauf offene, danach alle seit dem
 * Aufholpunkt geänderten (`updated_on`), täglich alle. Erledigt heißt
 * `is_closed`, nie ein frei benannter Status. Im selben Lauf übernimmt
 * {@see RedmineTimeImportService} die Zeiteinträge eingeschalteter Zuordnungen.
 *
 * @extends AbstractTaskImportService<RedmineProjectLink, RedmineConnection>
 */
class RedmineImportService extends AbstractTaskImportService {
    /** @var array<int, RedmineClient> ein Client je Organisation und Lauf (merkt sich die Statusliste) */
    private array $runClients = [];

    /** @var array<int, string> */
    private array $baseUrls = [];

    public function __construct(
        private readonly RedmineClientFactory $clients,
        private readonly RedmineTimeImportService $times,
    ) {}

    protected function pluginId(): string {
        return RedminePlugin::ID;
    }

    protected function externalType(): string {
        return RedminePlugin::EXT_TYPE_ISSUE;
    }

    protected function connectionModel(): string {
        return RedmineConnection::class;
    }

    protected function linkModel(): string {
        return RedmineProjectLink::class;
    }

    protected function isConfigured(int $organizationId): bool {
        return $this->clients->isConfigured($organizationId);
    }

    protected function reportsProgress(): bool {
        return false;
    }

    /**
     * Nur der Zeitimport, unter der Lauf-Sperre (TimeImporter-Einstieg).
     *
     * @return array{created: int, updated: int, skipped: int, unresolved_users: int, conflicts: int, removed: int, failed: int}
     */
    public function importTimes(Organization $organization): array {
        return $this->locked((int) $organization->id, function () use ($organization): array {
            $connection = $this->connection((int) $organization->id);
            if (! $this->mayRun($organization) || $connection === null) {
                return RedmineTimeImportService::emptyCounters();
            }

            try {
                return $this->times->import($organization, $connection, $this->runnableLinks((int) $organization->id));
            } catch (Throwable $e) {
                if ($this->isAuthFailure($e)) {
                    $connection->recordConnectionFailure($this->errorLabel($e));
                }

                throw $e;
            }
        });
    }

    /**
     * @param  RedmineConnection  $connection
     * @param  EloquentCollection<int, RedmineProjectLink>  $links
     */
    protected function afterImport(Organization $organization, Model $connection, EloquentCollection $links): void {
        try {
            $this->times->import($organization, $connection, $links);
        } catch (Throwable $e) {
            if ($this->isAuthFailure($e)) {
                $connection->recordConnectionFailure($this->errorLabel($e));
            }
            $this->recordError($e, $organization, ['phase' => 'time_entries']);
        }
    }

    /**
     * @param  RedmineConnection  $connection
     * @param  RedmineProjectLink  $link
     * @return iterable<array<string, mixed>>
     */
    protected function fetch(Model $connection, Model $link, ?CarbonImmutable $since, bool $full): iterable {
        $openOnly = ! $full && $since === null;
        foreach ($this->client($link->organizationId())->issues($link->sourceProjectId(), $openOnly, $full ? null : $since?->utc()->format('Y-m-d\TH:i:s\Z')) as $issue) {
            if (is_array($issue) && (string) data_get($issue, 'project.id') === $link->sourceProjectId()) {
                yield $issue;
            }
        }
    }

    /**
     * @param  RedmineConnection  $connection
     * @param  RedmineProjectLink  $link
     * @return array<string, mixed>|null
     */
    protected function fetchOne(Model $connection, Model $link, string $remoteId): ?array {
        $issue = $this->client($link->organizationId())->issue($remoteId);

        return $issue !== null && (string) data_get($issue, 'project.id') === $link->sourceProjectId() ? $issue : null;
    }

    /** @param array<string, mixed> $raw */
    protected function normalize(array $raw, Organization $organization): RemoteTask {
        $id = is_scalar($raw['id'] ?? null) ? (string) $raw['id'] : '';
        if (! ctype_digit($id)) {
            throw new IncompleteRemoteReadException('invalid_issue');
        }
        $mapped = [];
        $issues = [];

        $title = is_string($raw['subject'] ?? null) ? trim($raw['subject']) : '';
        if (array_key_exists('subject', $raw)) {
            $title === '' ? $issues[] = RemoteTask::EMPTY_TITLE : $mapped['title'] = mb_substr($title, 0, 255);
        }
        if (array_key_exists('description', $raw)) {
            $description = is_string($raw['description']) ? trim($raw['description']) : '';
            $mapped['description'] = $description !== '' ? $description : null;
        }
        if (array_key_exists('status', $raw)) {
            $closed = $this->client((int) $organization->id)->isClosedStatus($raw['status']);
            $closed !== null ? $mapped['status'] = ($closed ? TaskStatus::Done : TaskStatus::Open)->value : $issues[] = RemoteTask::UNKNOWN_STATUS;
        }
        if (array_key_exists('due_date', $raw)) {
            $due = $raw['due_date'];
            if ($due === null || (is_string($due) && DateHelper::isValidDate($due, ['Y-m-d']))) {
                $mapped['due_date'] = $due;
            }
        }

        $baseUrl = $this->baseUrls[(int) $organization->id] ??= (string) $this->connection((int) $organization->id)?->source_url;

        return new RemoteTask($id, $mapped, [
            'title' => $title,
            'key' => '#' . $id,
            'url' => $baseUrl !== '' ? $baseUrl . '/issues/' . $id : null,
            'project' => is_string(data_get($raw, 'project.name')) ? (string) data_get($raw, 'project.name') : null,
        ], $issues);
    }

    public function sourceUrl(ExternalReference $reference): ?string {
        $url = data_get($reference->payload, 'remote.url');
        $baseUrl = (string) $this->connection((int) $reference->organization_id)?->source_url;

        return is_string($url) && SelfHostedInstance::owns($baseUrl, $url) && preg_match('#/issues/\d+$#', $url) === 1 ? $url : null;
    }

    /**
     * Ein ungültiger Schlüssel wird bei Redmine nicht abgewiesen, sondern als
     * anonym behandelt — Listen kämen still nur mit Öffentlichem. Deshalb vor
     * dem ersten Abruf je Lauf das Konto prüfen (dort gibt es 401).
     */
    private function client(int $organizationId): RedmineClient {
        if (! isset($this->runClients[$organizationId])) {
            $client = $this->clients->for($organizationId);
            $client->currentUser();
            $this->runClients[$organizationId] = $client;
        }

        return $this->runClients[$organizationId];
    }
}
