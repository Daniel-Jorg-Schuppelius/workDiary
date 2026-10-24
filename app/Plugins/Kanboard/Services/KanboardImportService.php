<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KanboardImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Kanboard\Services;

use App\Enums\Task\TaskStatus;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\Organization;
use App\Plugins\Kanboard\Api\KanboardClientFactory;
use App\Plugins\Kanboard\KanboardPlugin;
use App\Plugins\Kanboard\Models\{KanboardConnection, KanboardProjectLink};
use App\Plugins\Support\TaskSync\{AbstractTaskImportService, IncompleteRemoteReadException, RemoteTask, SelfHostedInstance};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Kanboard-Adapter des Aufgabenimports (MVP-1341): Aufgaben je zugeordnetem
 * Projekt — Erstlauf nur offene, danach offene und geschlossene mit
 * `date_modification` ab dem Aufholpunkt, täglich alle. Status kommt allein
 * aus `is_active`; Spalten und Swimlanes sind Board-Positionen und bleiben
 * außen vor. Zeitpunkte liefert Kanboard als Unix-Sekunden, „0“ heißt leer.
 *
 * @extends AbstractTaskImportService<KanboardProjectLink, KanboardConnection>
 */
class KanboardImportService extends AbstractTaskImportService {
    /** @var array<int, string> Basis-URL je Organisation, einmal je Dienstinstanz gelesen */
    private array $baseUrls = [];

    public function __construct(private readonly KanboardClientFactory $clients) {}

    protected function pluginId(): string {
        return KanboardPlugin::ID;
    }

    protected function externalType(): string {
        return KanboardPlugin::EXT_TYPE_TASK;
    }

    protected function connectionModel(): string {
        return KanboardConnection::class;
    }

    protected function linkModel(): string {
        return KanboardProjectLink::class;
    }

    protected function isConfigured(int $organizationId): bool {
        return $this->clients->isConfigured($organizationId);
    }

    protected function reportsProgress(): bool {
        return false;
    }

    /**
     * @param  KanboardConnection  $connection
     * @param  KanboardProjectLink  $link
     * @return iterable<array<string, mixed>>
     */
    protected function fetch(Model $connection, Model $link, ?CarbonImmutable $since, bool $full): iterable {
        $tasks = $this->clients->for($link->organizationId())->tasks($link->sourceProjectId(), ! $full && $since === null);
        foreach ($tasks as $task) {
            if (! is_array($task) || (string) ($task['project_id'] ?? '') !== $link->sourceProjectId()) {
                continue;
            }
            $modified = $this->timestamp($task['date_modification'] ?? null);
            if (! $full && $since !== null && $modified !== null && $modified < $since->getTimestamp()) {
                continue;
            }
            yield $task;
        }
    }

    /**
     * @param  KanboardConnection  $connection
     * @param  KanboardProjectLink  $link
     * @return array<string, mixed>|null
     */
    protected function fetchOne(Model $connection, Model $link, string $remoteId): ?array {
        $task = $this->clients->for($link->organizationId())->task($remoteId);

        return $task !== null && (string) ($task['project_id'] ?? '') === $link->sourceProjectId() ? $task : null;
    }

    /** @param array<string, mixed> $raw */
    protected function normalize(array $raw, Organization $organization): RemoteTask {
        $id = is_scalar($raw['id'] ?? null) ? (string) $raw['id'] : '';
        if (! ctype_digit($id)) {
            throw new IncompleteRemoteReadException('invalid_task');
        }

        $values = [];
        if (array_key_exists('title', $raw)) {
            $values['title'] = is_string($raw['title']) ? $raw['title'] : null;
        }
        if (array_key_exists('description', $raw)) {
            $values['description'] = is_string($raw['description']) ? $raw['description'] : null;
        }
        if (array_key_exists('is_active', $raw)) {
            $values['status'] = match ((string) $raw['is_active']) {
                '1' => TaskStatus::Open,
                '0' => TaskStatus::Done,
                default => null,
            };
        }
        if (array_key_exists('date_due', $raw)) {
            $due = $this->timestamp($raw['date_due']);
            $values['due'] = $due !== null ? '@' . $due : (in_array($raw['date_due'], [null, 0, '0', ''], true) ? null : false);
        }
        [$mapped, $issues] = $this->mapFields($values, $organization);

        $baseUrl = $this->baseUrls[(int) $organization->id] ??= (string) $this->connection((int) $organization->id)?->source_url;
        $projectId = is_scalar($raw['project_id'] ?? null) ? (string) $raw['project_id'] : '';
        $reference = is_string($raw['reference'] ?? null) && $raw['reference'] !== '' ? $raw['reference'] : null;

        return new RemoteTask($id, $mapped, [
            'title' => trim((string) ($values['title'] ?? '')),
            'key' => $reference ?? '#' . $id,
            'url' => $baseUrl !== '' && ctype_digit($projectId)
                ? $baseUrl . '/?' . http_build_query(['controller' => 'TaskViewController', 'action' => 'show', 'task_id' => $id, 'project_id' => $projectId])
                : null,
            'project' => null,
        ], $issues);
    }

    public function sourceUrl(ExternalReference $reference): ?string {
        $url = data_get($reference->payload, 'remote.url');
        $baseUrl = (string) $this->connection((int) $reference->organization_id)?->source_url;

        return is_string($url) && SelfHostedInstance::owns($baseUrl, $url) ? $url : null;
    }

    /** Unix-Sekunden als Zahl oder Ziffernfolge; 0 und Unlesbares sind kein Zeitpunkt. */
    private function timestamp(mixed $value): ?int {
        $value = is_int($value) ? (string) $value : $value;

        return is_string($value) && ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }
}
