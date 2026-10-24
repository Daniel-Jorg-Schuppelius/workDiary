<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VikunjaImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Vikunja\Services;

use App\Enums\Task\TaskStatus;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\Organization;
use App\Plugins\Support\TaskSync\{AbstractTaskImportService, IncompleteRemoteReadException, RemoteTask, SelfHostedInstance};
use App\Plugins\Vikunja\Api\VikunjaClientFactory;
use App\Plugins\Vikunja\Models\{VikunjaConnection, VikunjaProjectLink};
use App\Plugins\Vikunja\VikunjaPlugin;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Database\Eloquent\Model;

/**
 * Vikunja-Adapter des Aufgabenimports (MVP-1340): Aufgaben je zugeordnetem
 * Projekt — Erstlauf nur nicht erledigte, danach alle mit `updated` ab dem
 * Aufholpunkt, täglich alle. Den Zeitfilter wendet der Adapter selbst an:
 * die Filtersprache von Vikunja kennt für Zeitpunkte nur Datumsausdrücke,
 * deren Auslegung von der Instanz-Zeitzone abhängt.
 *
 * @extends AbstractTaskImportService<VikunjaProjectLink, VikunjaConnection>
 */
class VikunjaImportService extends AbstractTaskImportService {
    /** Vikunja speichert „kein Fälligkeitsdatum“ als Nullzeitpunkt. */
    private const NO_DATE = '0001-01-01';

    /** @var array<int, string> Basis-URL je Organisation, einmal je Dienstinstanz gelesen */
    private array $baseUrls = [];

    public function __construct(private readonly VikunjaClientFactory $clients) {}

    protected function pluginId(): string {
        return VikunjaPlugin::ID;
    }

    protected function externalType(): string {
        return VikunjaPlugin::EXT_TYPE_TASK;
    }

    protected function connectionModel(): string {
        return VikunjaConnection::class;
    }

    protected function linkModel(): string {
        return VikunjaProjectLink::class;
    }

    protected function isConfigured(int $organizationId): bool {
        return $this->clients->isConfigured($organizationId);
    }

    protected function reportsProgress(): bool {
        return false;
    }

    /**
     * @param  VikunjaConnection  $connection
     * @param  VikunjaProjectLink  $link
     * @return iterable<array<string, mixed>>
     */
    protected function fetch(Model $connection, Model $link, ?CarbonImmutable $since, bool $full): iterable {
        $openOnly = ! $full && $since === null;
        foreach ($this->clients->for($link->organizationId())->tasks($link->sourceProjectId(), $openOnly) as $task) {
            if (! is_array($task) || (string) ($task['project_id'] ?? '') !== $link->sourceProjectId()) {
                continue;
            }
            if (! $full && $since !== null && ! $this->changedSince($task['updated'] ?? null, $since)) {
                continue;
            }
            yield $task;
        }
    }

    /**
     * @param  VikunjaConnection  $connection
     * @param  VikunjaProjectLink  $link
     * @return array<string, mixed>|null
     */
    protected function fetchOne(Model $connection, Model $link, string $remoteId): ?array {
        $task = $this->clients->for($link->organizationId())->task($remoteId);

        return $task !== null && (string) ($task['project_id'] ?? '') === $link->sourceProjectId() ? $task : null;
    }

    /** @param array<string, mixed> $raw */
    protected function normalize(array $raw, Organization $organization): RemoteTask {
        $id = is_int($raw['id'] ?? null) ? (string) $raw['id'] : '';
        if ($id === '' || ! ctype_digit($id)) {
            throw new IncompleteRemoteReadException('invalid_task');
        }

        $values = [];
        if (array_key_exists('title', $raw)) {
            $values['title'] = is_string($raw['title']) ? $raw['title'] : null;
        }
        if (array_key_exists('description', $raw)) {
            $values['description'] = is_string($raw['description']) ? StringHelper::htmlToText($raw['description']) : null;
        }
        if (array_key_exists('done', $raw)) {
            $values['status'] = is_bool($raw['done']) ? ($raw['done'] ? TaskStatus::Done : TaskStatus::Open) : null;
        }
        if (array_key_exists('due_date', $raw)) {
            $values['due'] = is_string($raw['due_date']) && str_starts_with($raw['due_date'], self::NO_DATE) ? null : $raw['due_date'];
        }
        [$mapped, $issues] = $this->mapFields($values, $organization);

        $baseUrl = $this->baseUrls[(int) $organization->id] ??= (string) $this->connection((int) $organization->id)?->source_url;
        $identifier = is_string($raw['identifier'] ?? null) && $raw['identifier'] !== '' ? $raw['identifier'] : null;

        return new RemoteTask($id, $mapped, [
            'title' => trim((string) ($values['title'] ?? '')),
            'key' => $identifier ?? (is_int($raw['index'] ?? null) ? '#' . $raw['index'] : null),
            'url' => $baseUrl !== '' ? $baseUrl . '/tasks/' . $id : null,
            'project' => null,
        ], $issues);
    }

    public function sourceUrl(ExternalReference $reference): ?string {
        $url = data_get($reference->payload, 'remote.url');
        $baseUrl = (string) $this->connection((int) $reference->organization_id)?->source_url;

        return is_string($url) && SelfHostedInstance::owns($baseUrl, $url) ? $url : null;
    }
}
