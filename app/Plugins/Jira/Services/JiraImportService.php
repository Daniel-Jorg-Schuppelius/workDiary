<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JiraImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Jira\Services;

use App\Enums\Task\TaskStatus;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\Organization;
use App\Plugins\Jira\Api\JiraClientFactory;
use App\Plugins\Jira\JiraPlugin;
use App\Plugins\Jira\Models\{JiraConnection, JiraProjectLink};
use App\Plugins\Support\TaskSync\{AbstractTaskImportService, IncompleteRemoteReadException, RemoteTask};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\DateHelper;
use Illuminate\Database\Eloquent\Model;

/**
 * Jira-Adapter des Aufgabenimports (MVP-1306): Enhanced Search je
 * Projektzuordnung — Erstlauf offene Vorgänge, danach Änderungen seit dem
 * Aufholpunkt, täglich alle. Status nur über die Statuskategorie (`new`,
 * `indeterminate`, `done`), nie über lokalisierte Namen; Beschreibung als
 * begrenzte ADF-Textprojektion. Identität ist die Issue-ID, der Schlüssel
 * dient nur der Anzeige.
 *
 * @extends AbstractTaskImportService<JiraProjectLink, JiraConnection>
 */
class JiraImportService extends AbstractTaskImportService {
    /** @var array<int, string> Site-Adresse je Organisation, einmal je Dienstinstanz gelesen */
    private array $siteUrls = [];

    public function __construct(private readonly JiraClientFactory $clients) {}

    protected function pluginId(): string {
        return JiraPlugin::ID;
    }

    protected function externalType(): string {
        return JiraPlugin::EXT_TYPE_ISSUE;
    }

    protected function connectionModel(): string {
        return JiraConnection::class;
    }

    protected function linkModel(): string {
        return JiraProjectLink::class;
    }

    protected function isConfigured(int $organizationId): bool {
        return $this->clients->isConfigured();
    }

    /**
     * @param  JiraConnection  $connection
     * @param  JiraProjectLink  $link
     * @return iterable<array<string, mixed>>
     */
    protected function fetch(Model $connection, Model $link, ?CarbonImmutable $since, bool $full): iterable {
        // Relative Grenze statt Datum: JQL-Daten gelten in der Zeitzone des Jira-Kontos.
        $condition = match (true) {
            $full => '',
            $since === null => 'statusCategory != Done',
            default => 'updated >= -' . max(1, (int) ceil($since->diffInMinutes(CarbonImmutable::now(), true))) . 'm',
        };

        foreach ($this->clients->for($link->organizationId())->issues($link->sourceProjectId(), $condition) as $issue) {
            if (is_array($issue) && (string) data_get($issue, 'fields.project.id') === $link->sourceProjectId()) {
                yield $issue;
            }
        }
    }

    /**
     * @param  JiraConnection  $connection
     * @param  JiraProjectLink  $link
     * @return array<string, mixed>|null
     */
    protected function fetchOne(Model $connection, Model $link, string $remoteId): ?array {
        return $this->clients->for((int) $connection->organization_id)->issue($remoteId);
    }

    /** @param array<string, mixed> $raw */
    protected function normalize(array $raw, Organization $organization): RemoteTask {
        $id = is_scalar($raw['id'] ?? null) ? (string) $raw['id'] : '';
        if (! ctype_digit($id)) {
            throw new IncompleteRemoteReadException('invalid_issue');
        }
        $fields = is_array($raw['fields'] ?? null) ? $raw['fields'] : [];
        $key = is_string($raw['key'] ?? null) && preg_match('/^[A-Z][A-Z0-9_]*-\d+$/', $raw['key']) === 1 ? $raw['key'] : null;
        $mapped = [];
        $issues = [];

        $title = is_string($fields['summary'] ?? null) ? trim($fields['summary']) : '';
        if (array_key_exists('summary', $fields)) {
            $title === '' ? $issues[] = RemoteTask::EMPTY_TITLE : $mapped['title'] = mb_substr($title, 0, 255);
        }
        if (array_key_exists('description', $fields)) {
            $description = AdfText::convert($fields['description']);
            $mapped['description'] = $description['text'];
            if (! $description['complete']) {
                $issues[] = RemoteTask::DESCRIPTION_INCOMPLETE;
            }
        }
        if (array_key_exists('status', $fields)) {
            $status = match (data_get($fields, 'status.statusCategory.key')) {
                'new' => TaskStatus::Open,
                'indeterminate' => TaskStatus::InProgress,
                'done' => TaskStatus::Done,
                default => null,
            };
            $status !== null ? $mapped['status'] = $status->value : $issues[] = RemoteTask::UNKNOWN_STATUS;
        }
        if (array_key_exists('duedate', $fields)) {
            $due = $fields['duedate'];
            if ($due === null || (is_string($due) && DateHelper::isValidDate($due, ['Y-m-d']))) {
                $mapped['due_date'] = $due;
            }
        }

        $site = $this->siteUrls[(int) $organization->id] ??= (string) $this->connection((int) $organization->id)?->source_url;

        return new RemoteTask($id, $mapped, [
            'title' => $title,
            'key' => $key,
            'url' => $key !== null && JiraPlugin::isSiteUrl($site) ? rtrim($site, '/') . '/browse/' . $key : null,
            'project' => is_string(data_get($fields, 'project.name')) ? (string) data_get($fields, 'project.name') : null,
        ], $issues);
    }

    public function sourceUrl(ExternalReference $reference): ?string {
        $url = data_get($reference->payload, 'remote.url');
        if (! is_string($url) || preg_match('#^(https://[a-z0-9-]+\.atlassian\.net)/browse/[A-Z][A-Z0-9_]*-\d+$#i', $url, $m) !== 1) {
            return null;
        }

        return JiraPlugin::isSiteUrl($m[1]) ? $url : null;
    }
}
