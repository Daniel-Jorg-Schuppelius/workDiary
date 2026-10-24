<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaigaImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Taiga\Services;

use App\Enums\Task\TaskStatus;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\Organization;
use App\Plugins\Support\TaskSync\{AbstractTaskImportService, IncompleteRemoteReadException, RemoteTask, SelfHostedInstance};
use App\Plugins\Taiga\Api\TaigaClientFactory;
use App\Plugins\Taiga\Models\{TaigaConnection, TaigaProjectLink};
use App\Plugins\Taiga\TaigaPlugin;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Taiga-Adapter des Aufgabenimports (MVP-1343): Userstories je zugeordnetem
 * Projekt — Erstlauf nicht geschlossene, danach `modified_date__gte` und
 * `finish_date__gte` ab dem Aufholpunkt, täglich alle. Die Liste liefert
 * keine Beschreibung; der Einzelabruf folgt nur für neue oder seit dem
 * letzten Lauf geänderte Userstories, sonst fehlt das Feld (fehlend ≠
 * gelöscht). Status allein aus `is_closed`.
 *
 * @extends AbstractTaskImportService<TaigaProjectLink, TaigaConnection>
 */
class TaigaImportService extends AbstractTaskImportService {
    /** @var array<int, string> App-Adresse je Organisation, einmal je Dienstinstanz gelesen */
    private array $appUrls = [];

    public function __construct(private readonly TaigaClientFactory $clients) {}

    protected function pluginId(): string {
        return TaigaPlugin::ID;
    }

    protected function externalType(): string {
        return TaigaPlugin::EXT_TYPE_USER_STORY;
    }

    protected function connectionModel(): string {
        return TaigaConnection::class;
    }

    protected function linkModel(): string {
        return TaigaProjectLink::class;
    }

    protected function isConfigured(int $organizationId): bool {
        return $this->clients->isConfigured($organizationId);
    }

    protected function reportsProgress(): bool {
        return false;
    }

    /**
     * @param  TaigaConnection  $connection
     * @param  TaigaProjectLink  $link
     * @return iterable<array<string, mixed>>
     */
    protected function fetch(Model $connection, Model $link, ?CarbonImmutable $since, bool $full): iterable {
        $client = $this->clients->for($link->organizationId());
        // Ohne `view_us` antwortet Taiga mit einer leeren Liste statt mit einem Fehler.
        if (! in_array('view_us', $client->projectPermissions($link->sourceProjectId()), true)) {
            throw new IncompleteRemoteReadException('missing_permission');
        }
        $detailSince = $full ? $link->checkpointAt()?->subMinutes(self::OVERLAP_MINUTES) : $since;
        $yielded = [];
        foreach ($this->passes($since, $full) as $filters) {
            foreach ($client->userStories($link->sourceProjectId(), $filters) as $story) {
                $id = is_array($story) && is_scalar($story['id'] ?? null) ? (string) $story['id'] : '';
                if ($id === '' || isset($yielded[$id]) || (string) ($story['project'] ?? '') !== $link->sourceProjectId()) {
                    continue;
                }
                $yielded[$id] = true;
                if ($detailSince === null || $this->changedSince($story['modified_date'] ?? null, $detailSince)) {
                    $story = $client->userStory($id) ?? $story;
                }
                yield $story;
            }
        }
    }

    /**
     * Listenfilter je Lauf. Schließt Taiga eine Userstory über ihre Tasks,
     * speichert es nur `is_closed` und `finish_date`, nicht `modified_date` —
     * der Folgelauf fragt deshalb zusätzlich nach `finish_date`. Eine
     * Wiedereröffnung über Tasks sieht erst der Volllauf.
     *
     * @return list<array<string, string>>
     */
    private function passes(?CarbonImmutable $since, bool $full): array {
        if ($full) {
            return [[]];
        }
        if ($since === null) {
            return [['is_closed' => 'false']];
        }
        $at = $since->utc()->toIso8601String();

        return [['modified_date__gte' => $at], ['finish_date__gte' => $at]];
    }

    /**
     * @param  TaigaConnection  $connection
     * @param  TaigaProjectLink  $link
     * @return array<string, mixed>|null
     */
    protected function fetchOne(Model $connection, Model $link, string $remoteId): ?array {
        $story = $this->clients->for($link->organizationId())->userStory($remoteId);

        return $story !== null && (string) ($story['project'] ?? '') === $link->sourceProjectId() ? $story : null;
    }

    /** @param array<string, mixed> $raw */
    protected function normalize(array $raw, Organization $organization): RemoteTask {
        $id = is_scalar($raw['id'] ?? null) ? (string) $raw['id'] : '';
        if (! ctype_digit($id)) {
            throw new IncompleteRemoteReadException('invalid_user_story');
        }

        $values = [];
        if (array_key_exists('subject', $raw)) {
            $values['title'] = is_string($raw['subject']) ? $raw['subject'] : null;
        }
        if (array_key_exists('description', $raw)) {
            $values['description'] = is_string($raw['description']) ? $raw['description'] : null;
        }
        if (array_key_exists('is_closed', $raw)) {
            $values['status'] = is_bool($raw['is_closed']) ? ($raw['is_closed'] ? TaskStatus::Done : TaskStatus::Open) : null;
        }
        if (array_key_exists('due_date', $raw)) {
            $values['due_on'] = is_string($raw['due_date']) ? substr($raw['due_date'], 0, 10) : null;
        }
        [$mapped, $issues] = $this->mapFields($values, $organization);

        $appUrl = $this->appUrls[(int) $organization->id] ??= (string) $this->connection((int) $organization->id)?->source_url;
        $slug = data_get($raw, 'project_extra_info.slug');
        $ref = is_int($raw['ref'] ?? null) ? $raw['ref'] : null;

        return new RemoteTask($id, $mapped, [
            'title' => trim((string) ($values['title'] ?? '')),
            'key' => $ref !== null ? '#' . $ref : null,
            'url' => $appUrl !== '' && is_string($slug) && preg_match('/^[A-Za-z0-9_-]+$/', $slug) === 1 && $ref !== null
                ? $appUrl . '/project/' . $slug . '/us/' . $ref
                : null,
            'project' => is_string(data_get($raw, 'project_extra_info.name')) ? (string) data_get($raw, 'project_extra_info.name') : null,
        ], $issues);
    }

    public function sourceUrl(ExternalReference $reference): ?string {
        $url = data_get($reference->payload, 'remote.url');
        $appUrl = (string) $this->connection((int) $reference->organization_id)?->source_url;

        return is_string($url) && SelfHostedInstance::owns($appUrl, $url) ? $url : null;
    }
}
