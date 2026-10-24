<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GiteaImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Gitea\Services;

use App\Enums\Task\TaskStatus;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\Organization;
use App\Plugins\Gitea\Api\GiteaClientFactory;
use App\Plugins\Gitea\GiteaPlugin;
use App\Plugins\Gitea\Models\{GiteaConnection, GiteaProjectLink};
use App\Plugins\Support\TaskSync\{AbstractTaskImportService, IncompleteRemoteReadException, RemoteTask, SelfHostedInstance};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Gitea-/Forgejo-Adapter des Aufgabenimports (MVP-1319): Issues je
 * zugeordnetem Repository — Erstlauf offene, danach alle seit dem Aufholpunkt
 * geänderten (`since`), täglich alle. Das Repository wird über seine stabile
 * ID aufgelöst, ein Umbenennen stört nicht. Identität ist die instanzweite
 * Issue-ID, die Nummer dient nur der Anzeige; Pull Requests bleiben außen vor.
 *
 * @extends AbstractTaskImportService<GiteaProjectLink, GiteaConnection>
 */
class GiteaImportService extends AbstractTaskImportService {
    /** @var array<int, string> Basis-URL je Organisation, einmal je Dienstinstanz gelesen */
    private array $baseUrls = [];

    public function __construct(private readonly GiteaClientFactory $clients) {}

    protected function pluginId(): string {
        return GiteaPlugin::ID;
    }

    protected function externalType(): string {
        return GiteaPlugin::EXT_TYPE_ISSUE;
    }

    protected function connectionModel(): string {
        return GiteaConnection::class;
    }

    protected function linkModel(): string {
        return GiteaProjectLink::class;
    }

    protected function isConfigured(int $organizationId): bool {
        return $this->clients->isConfigured($organizationId);
    }

    protected function reportsProgress(): bool {
        return false;
    }

    /**
     * @param  GiteaConnection  $connection
     * @param  GiteaProjectLink  $link
     * @return iterable<array<string, mixed>>
     */
    protected function fetch(Model $connection, Model $link, ?CarbonImmutable $since, bool $full): iterable {
        $client = $this->clients->for($link->organizationId());
        $repository = $client->repository($link->sourceProjectId());
        if (! is_string($repository['full_name'] ?? null)) {
            throw new IncompleteRemoteReadException('repository_missing');
        }

        $state = $full || $since !== null ? 'all' : 'open';
        foreach ($client->issues($repository['full_name'], $state, $full ? null : $since?->utc()->toRfc3339String()) as $issue) {
            if (! is_array($issue) || ($issue['pull_request'] ?? null) !== null) {
                continue;
            }
            // Ein Ladefehler bei Gitea liefert ein leeres Issue ohne Repository — kein stilles Auslassen.
            if ((string) data_get($issue, 'repository.id') !== $link->sourceProjectId()) {
                throw new IncompleteRemoteReadException('invalid_issue');
            }
            yield $issue;
        }
    }

    /**
     * Gitea liest Issues nur über Repository und Nummer — das Verknüpfen sucht
     * die ID unter den offenen Issues der Zuordnung (die Vorschau zeigt nur diese).
     *
     * @param  GiteaConnection  $connection
     * @param  GiteaProjectLink  $link
     * @return array<string, mixed>|null
     */
    protected function fetchOne(Model $connection, Model $link, string $remoteId): ?array {
        foreach ($this->fetch($connection, $link, null, false) as $issue) {
            if ((string) ($issue['id'] ?? '') === $remoteId) {
                return $issue;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $raw */
    protected function normalize(array $raw, Organization $organization): RemoteTask {
        $id = is_scalar($raw['id'] ?? null) ? (string) $raw['id'] : '';
        if (! ctype_digit($id)) {
            throw new IncompleteRemoteReadException('invalid_issue');
        }
        $mapped = [];
        $issues = [];

        $title = is_string($raw['title'] ?? null) ? trim($raw['title']) : '';
        if (array_key_exists('title', $raw)) {
            $title === '' ? $issues[] = RemoteTask::EMPTY_TITLE : $mapped['title'] = mb_substr($title, 0, 255);
        }
        if (array_key_exists('body', $raw)) {
            $body = is_string($raw['body']) ? trim($raw['body']) : '';
            $mapped['description'] = $body !== '' ? $body : null;
        }
        if (array_key_exists('state', $raw)) {
            $status = match ($raw['state']) {
                'open' => TaskStatus::Open,
                'closed' => TaskStatus::Done,
                default => null,
            };
            $status !== null ? $mapped['status'] = $status->value : $issues[] = RemoteTask::UNKNOWN_STATUS;
        }
        // Gitea speichert die Frist als reines Datum (23:59:59 in der Serverzone) — das Datum gilt ohne Umrechnung.
        if (array_key_exists('due_date', $raw)) {
            if ($raw['due_date'] === null) {
                $mapped['due_date'] = null;
            } elseif (is_string($raw['due_date']) && preg_match('/^\d{4}-\d{2}-\d{2}/', $raw['due_date']) === 1) {
                $mapped['due_date'] = substr($raw['due_date'], 0, 10);
            }
        }

        $baseUrl = $this->baseUrls[(int) $organization->id] ??= (string) $this->connection((int) $organization->id)?->source_url;
        $url = is_string($raw['html_url'] ?? null) && SelfHostedInstance::owns($baseUrl, $raw['html_url']) ? $raw['html_url'] : null;

        return new RemoteTask($id, $mapped, [
            'title' => $title,
            'key' => is_scalar($raw['number'] ?? null) ? '#' . $raw['number'] : null,
            'url' => $url,
            'project' => is_string(data_get($raw, 'repository.full_name')) ? (string) data_get($raw, 'repository.full_name') : null,
        ], $issues);
    }

    public function sourceUrl(ExternalReference $reference): ?string {
        $url = data_get($reference->payload, 'remote.url');
        $baseUrl = (string) $this->connection((int) $reference->organization_id)?->source_url;

        return is_string($url) && SelfHostedInstance::owns($baseUrl, $url) ? $url : null;
    }
}
