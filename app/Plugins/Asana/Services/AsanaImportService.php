<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AsanaImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Asana\Services;

use App\Enums\Task\TaskStatus;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\Organization;
use App\Plugins\Asana\Api\AsanaClientFactory;
use App\Plugins\Asana\AsanaPlugin;
use App\Plugins\Asana\Models\{AsanaConnection, AsanaProjectLink};
use App\Plugins\Support\TaskSync\{AbstractTaskImportService, IncompleteRemoteReadException, RemoteTask};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\DateHelper;
use Illuminate\Database\Eloquent\Model;

/**
 * Asana-Adapter des Aufgabenimports (MVP-1308): Aufgaben je zugeordnetem
 * Projekt mit `completed_since` — Erstlauf ab Startzeitpunkt (nur
 * unerledigte), danach ab dem Aufholpunkt, täglich ab einer weit
 * zurückliegenden Grenze. Eine Aufgabe in mehreren zugeordneten Projekten
 * führt die Engine nach GID zusammen. `completed=false` meldet keinen
 * Bearbeitungsstand und erhält deshalb ein lokales „in Bearbeitung“.
 *
 * @extends AbstractTaskImportService<AsanaProjectLink, AsanaConnection>
 */
class AsanaImportService extends AbstractTaskImportService {
    /** Grenze des Vollabgleichs: liest auch alte erledigte Aufgaben. */
    private const FULL_SINCE = '1970-01-01T00:00:00Z';

    public function __construct(private readonly AsanaClientFactory $clients) {}

    protected function pluginId(): string {
        return AsanaPlugin::ID;
    }

    protected function externalType(): string {
        return AsanaPlugin::EXT_TYPE_TASK;
    }

    protected function connectionModel(): string {
        return AsanaConnection::class;
    }

    protected function linkModel(): string {
        return AsanaProjectLink::class;
    }

    protected function isConfigured(int $organizationId): bool {
        return $this->clients->isConfigured();
    }

    /**
     * @param  AsanaConnection  $connection
     * @param  AsanaProjectLink  $link
     * @return iterable<array<string, mixed>>
     */
    protected function fetch(Model $connection, Model $link, ?CarbonImmutable $since, bool $full): iterable {
        $completedSince = match (true) {
            $full => self::FULL_SINCE,
            $since === null => CarbonImmutable::now('UTC')->toIso8601ZuluString(),
            default => $since->utc()->toIso8601ZuluString(),
        };

        foreach ($this->clients->for($link->organizationId())->projectTasks($link->sourceProjectId(), $completedSince) as $task) {
            $memberships = data_get($task, 'memberships');
            if (is_array($memberships) && ! collect($memberships)->contains(fn ($m): bool => (string) data_get($m, 'project.gid') === $link->sourceProjectId())) {
                continue;
            }
            yield $task;
        }
    }

    /**
     * @param  AsanaConnection  $connection
     * @param  AsanaProjectLink  $link
     * @return array<string, mixed>|null
     */
    protected function fetchOne(Model $connection, Model $link, string $remoteId): ?array {
        return $this->clients->for((int) $connection->organization_id)->task($remoteId);
    }

    /** @param array<string, mixed> $raw */
    protected function normalize(array $raw, Organization $organization): RemoteTask {
        if (! AsanaPlugin::isGid($raw['gid'] ?? null)) {
            throw new IncompleteRemoteReadException('invalid_task');
        }
        $mapped = [];
        $issues = [];

        $title = is_string($raw['name'] ?? null) ? trim($raw['name']) : '';
        if (array_key_exists('name', $raw)) {
            $title === '' ? $issues[] = RemoteTask::EMPTY_TITLE : $mapped['title'] = mb_substr($title, 0, 255);
        }
        if (array_key_exists('notes', $raw)) {
            $notes = is_string($raw['notes']) ? trim($raw['notes']) : '';
            $mapped['description'] = $notes !== '' ? $notes : null;
        }
        if (array_key_exists('completed', $raw)) {
            is_bool($raw['completed'])
                ? $mapped['status'] = ($raw['completed'] ? TaskStatus::Done : TaskStatus::Open)->value
                : $issues[] = RemoteTask::UNKNOWN_STATUS;
        }
        if (array_key_exists('due_on', $raw) || array_key_exists('due_at', $raw)) {
            $due = $this->dueDate($raw['due_on'] ?? null, $raw['due_at'] ?? null, $organization);
            if ($due !== false) {
                $mapped['due_date'] = $due;
            }
        }

        $url = is_string($raw['permalink_url'] ?? null) && self::isTaskUrl($raw['permalink_url']) ? $raw['permalink_url'] : null;

        return new RemoteTask((string) $raw['gid'], $mapped, ['title' => $title, 'key' => null, 'url' => $url, 'project' => null], $issues);
    }

    protected function reportsProgress(): bool {
        return false;
    }

    public function sourceUrl(ExternalReference $reference): ?string {
        $url = data_get($reference->payload, 'remote.url');

        return is_string($url) && self::isTaskUrl($url) ? $url : null;
    }

    /** Nur https auf app.asana.com, ohne Benutzerinfo, Port oder Fragment. */
    private static function isTaskUrl(string $url): bool {
        return preg_match('#^https://app\.asana\.com/[0-9A-Za-z/_-]{1,200}$#', $url) === 1;
    }

    /**
     * Fälligkeit als Datum: `due_on` direkt, sonst `due_at` in der Zeitzone
     * der Organisation; false = keine verwertbare Angabe (Feld bleibt unberührt).
     */
    private function dueDate(mixed $dueOn, mixed $dueAt, Organization $organization): string|false|null {
        if (is_string($dueOn) && DateHelper::isValidDate($dueOn, ['Y-m-d'])) {
            return $dueOn;
        }

        return $dueOn === null ? $this->localDate($dueAt, $organization) : false;
    }
}
