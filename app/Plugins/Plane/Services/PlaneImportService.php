<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlaneImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Plane\Services;

use App\Enums\Task\TaskStatus;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\Organization;
use App\Plugins\Plane\Api\PlaneClientFactory;
use App\Plugins\Plane\Models\{PlaneConnection, PlaneProjectLink};
use App\Plugins\Plane\PlanePlugin;
use App\Plugins\Support\TaskSync\{AbstractTaskImportService, IncompleteRemoteReadException, RemoteTask, SelfHostedInstance};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Database\Eloquent\Model;

/**
 * Plane-Adapter des Aufgabenimports (MVP-1342): Arbeitspakete je
 * zugeordnetem Projekt. Status kommt aus der Gruppe des Zustands
 * (backlog/unstarted/triage → offen, started → in Bearbeitung,
 * completed/cancelled → erledigt), nie aus dem frei benennbaren Zustandsnamen.
 * Die API kennt keinen Änderungsfilter: der Erstlauf übernimmt nur nicht
 * abgeschlossene, Folgeläufe vergleichen nur ab dem Aufholpunkt geänderte.
 *
 * @extends AbstractTaskImportService<PlaneProjectLink, PlaneConnection>
 */
class PlaneImportService extends AbstractTaskImportService {
    private const GROUP_KEY = '_state_group';

    /** @var array<int, string> Workspace-Adresse je Organisation, einmal je Dienstinstanz gelesen */
    private array $workspaceUrls = [];

    public function __construct(private readonly PlaneClientFactory $clients) {}

    protected function pluginId(): string {
        return PlanePlugin::ID;
    }

    protected function externalType(): string {
        return PlanePlugin::EXT_TYPE_WORK_ITEM;
    }

    protected function connectionModel(): string {
        return PlaneConnection::class;
    }

    protected function linkModel(): string {
        return PlaneProjectLink::class;
    }

    protected function isConfigured(int $organizationId): bool {
        return $this->clients->isConfigured($organizationId);
    }

    /**
     * @param  PlaneConnection  $connection
     * @param  PlaneProjectLink  $link
     * @return iterable<array<string, mixed>>
     */
    protected function fetch(Model $connection, Model $link, ?CarbonImmutable $since, bool $full): iterable {
        $client = $this->clients->for($link->organizationId());
        $groups = $client->stateGroups($link->sourceProjectId());
        foreach ($client->workItems($link->sourceProjectId()) as $item) {
            if (! is_array($item) || strtolower((string) ($item['project'] ?? '')) !== strtolower($link->sourceProjectId())) {
                continue;
            }
            $item[self::GROUP_KEY] = $this->stateGroup($item, $groups);
            if (! $full && $since === null && in_array($item[self::GROUP_KEY], ['completed', 'cancelled'], true)) {
                continue;
            }
            if (! $full && $since !== null && ! $this->changedSince($item['updated_at'] ?? null, $since)) {
                continue;
            }
            yield $item;
        }
    }

    /**
     * @param  PlaneConnection  $connection
     * @param  PlaneProjectLink  $link
     * @return array<string, mixed>|null
     */
    protected function fetchOne(Model $connection, Model $link, string $remoteId): ?array {
        $client = $this->clients->for($link->organizationId());
        $item = $client->workItem($link->sourceProjectId(), $remoteId);
        if ($item === null || strtolower((string) ($item['project'] ?? '')) !== strtolower($link->sourceProjectId())) {
            return null;
        }
        $item[self::GROUP_KEY] = $this->stateGroup($item, $client->stateGroups($link->sourceProjectId()));

        return $item;
    }

    /** @param array<string, mixed> $raw */
    protected function normalize(array $raw, Organization $organization): RemoteTask {
        $id = is_string($raw['id'] ?? null) ? strtolower($raw['id']) : '';
        $projectId = is_string($raw['project'] ?? null) ? strtolower($raw['project']) : '';
        if (preg_match('/^[0-9a-f-]{36}$/', $id) !== 1 || preg_match('/^[0-9a-f-]{36}$/', $projectId) !== 1) {
            throw new IncompleteRemoteReadException('invalid_work_item');
        }

        $values = [];
        if (array_key_exists('name', $raw)) {
            $values['title'] = is_string($raw['name']) ? $raw['name'] : null;
        }
        if (array_key_exists('description_html', $raw)) {
            $values['description'] = is_string($raw['description_html']) ? StringHelper::htmlToText($raw['description_html']) : null;
        } elseif (array_key_exists('description_stripped', $raw)) {
            $values['description'] = is_string($raw['description_stripped']) ? $raw['description_stripped'] : null;
        }
        if (array_key_exists('state', $raw)) {
            $values['status'] = match ($raw[self::GROUP_KEY] ?? null) {
                'backlog', 'unstarted', 'triage' => TaskStatus::Open,
                'started' => TaskStatus::InProgress,
                'completed', 'cancelled' => TaskStatus::Done,
                default => null,
            };
        }
        if (array_key_exists('target_date', $raw)) {
            $values['due_on'] = is_string($raw['target_date']) ? substr($raw['target_date'], 0, 10) : null;
        }
        [$mapped, $issues] = $this->mapFields($values, $organization);

        $workspaceUrl = $this->workspaceUrls[(int) $organization->id] ??= (string) $this->connection((int) $organization->id)?->source_url;

        return new RemoteTask($id, $mapped, [
            'title' => trim((string) ($values['title'] ?? '')),
            'key' => is_int($raw['sequence_id'] ?? null) ? '#' . $raw['sequence_id'] : null,
            'url' => $workspaceUrl !== '' ? $workspaceUrl . '/projects/' . $projectId . '/issues/' . $id : null,
            'project' => null,
        ], $issues);
    }

    public function sourceUrl(ExternalReference $reference): ?string {
        $url = data_get($reference->payload, 'remote.url');
        $workspaceUrl = (string) $this->connection((int) $reference->organization_id)?->source_url;

        return is_string($url) && SelfHostedInstance::owns($workspaceUrl, $url) ? $url : null;
    }

    /**
     * Gruppe des Zustands: `state` ist ohne `expand` eine UUID, aufgeklappt ein Objekt mit `group`.
     *
     * @param  array<string, mixed>  $item
     * @param  array<string, string>  $groups
     */
    private function stateGroup(array $item, array $groups): ?string {
        $state = $item['state'] ?? null;
        if (is_array($state)) {
            return is_string($state['group'] ?? null) ? $state['group'] : null;
        }

        return is_string($state) ? $groups[$state] ?? null : null;
    }
}
