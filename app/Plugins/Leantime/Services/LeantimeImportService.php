<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeantimeImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Leantime\Services;

use App\Enums\Task\TaskStatus;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\Organization;
use App\Plugins\Leantime\Api\LeantimeClientFactory;
use App\Plugins\Leantime\LeantimePlugin;
use App\Plugins\Leantime\Models\{LeantimeConnection, LeantimeProjectLink};
use App\Plugins\Support\TaskSync\{AbstractTaskImportService, IncompleteRemoteReadException, RemoteTask, SelfHostedInstance};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Database\Eloquent\Model;

/**
 * Leantime-Adapter des Aufgabenimports (MVP-1344): To-dos je zugeordnetem
 * Projekt. Status kommt aus der Statusart des projekteigenen Labels (NEW →
 * offen, INPROGRESS → in Bearbeitung, DONE → erledigt), nie aus dem frei
 * benennbaren Namen. Leantime speichert Zeitpunkte in UTC und ohne Zone;
 * „0000-00-00“ und „1969-12-31 00:00:00“ heißen leer. Archivierte Tickets
 * (Status -1) gelten als erledigt. Erst neuere Versionen kennen einen
 * Änderungsfilter — der Adapter liest daher alle und vergleicht nur ab dem
 * Aufholpunkt geänderte.
 *
 * @extends AbstractTaskImportService<LeantimeProjectLink, LeantimeConnection>
 */
class LeantimeImportService extends AbstractTaskImportService {
    private const TYPE_KEY = '_status_type';

    /** So speichert Leantime ein geleertes Datum neben dem Nulldatum. */
    private const EPOCH_EMPTY = '1969-12-31 00:00:00';

    /** @var array<int, string> Basis-URL je Organisation, einmal je Dienstinstanz gelesen */
    private array $baseUrls = [];

    public function __construct(private readonly LeantimeClientFactory $clients) {}

    protected function pluginId(): string {
        return LeantimePlugin::ID;
    }

    protected function externalType(): string {
        return LeantimePlugin::EXT_TYPE_TICKET;
    }

    protected function connectionModel(): string {
        return LeantimeConnection::class;
    }

    protected function linkModel(): string {
        return LeantimeProjectLink::class;
    }

    protected function isConfigured(int $organizationId): bool {
        return $this->clients->isConfigured($organizationId);
    }

    /**
     * @param  LeantimeConnection  $connection
     * @param  LeantimeProjectLink  $link
     * @return iterable<array<string, mixed>>
     */
    protected function fetch(Model $connection, Model $link, ?CarbonImmutable $since, bool $full): iterable {
        $client = $this->clients->for($link->organizationId());
        $types = $client->statusTypes($link->sourceProjectId());
        foreach ($client->tickets($link->sourceProjectId()) as $ticket) {
            if (! is_array($ticket) || (string) ($ticket['projectId'] ?? '') !== $link->sourceProjectId() || ($ticket['type'] ?? null) === 'milestone') {
                continue;
            }
            $ticket[self::TYPE_KEY] = $types[(string) ($ticket['status'] ?? '')] ?? null;
            if (! $full && $since === null && $ticket[self::TYPE_KEY] === 'DONE') {
                continue;
            }
            if (! $full && $since !== null && ! $this->changedSince($this->utc($ticket['modified'] ?? null), $since)) {
                continue;
            }
            yield $ticket;
        }
    }

    /**
     * @param  LeantimeConnection  $connection
     * @param  LeantimeProjectLink  $link
     * @return array<string, mixed>|null
     */
    protected function fetchOne(Model $connection, Model $link, string $remoteId): ?array {
        $client = $this->clients->for($link->organizationId());
        $ticket = $client->ticket($remoteId);
        if ($ticket === null || (string) ($ticket['projectId'] ?? '') !== $link->sourceProjectId() || ($ticket['type'] ?? null) === 'milestone') {
            return null;
        }
        $ticket[self::TYPE_KEY] = $client->statusTypes($link->sourceProjectId())[(string) ($ticket['status'] ?? '')] ?? null;

        return $ticket;
    }

    /** @param array<string, mixed> $raw */
    protected function normalize(array $raw, Organization $organization): RemoteTask {
        $id = is_scalar($raw['id'] ?? null) ? (string) $raw['id'] : '';
        if (! ctype_digit($id)) {
            throw new IncompleteRemoteReadException('invalid_ticket');
        }

        $values = [];
        if (array_key_exists('headline', $raw)) {
            $values['title'] = is_string($raw['headline']) ? $raw['headline'] : null;
        }
        if (array_key_exists('description', $raw)) {
            $values['description'] = is_string($raw['description']) ? StringHelper::htmlToText($raw['description']) : null;
        }
        if (array_key_exists('status', $raw)) {
            $values['status'] = match ($raw[self::TYPE_KEY] ?? null) {
                'NEW' => TaskStatus::Open,
                'INPROGRESS' => TaskStatus::InProgress,
                'DONE' => TaskStatus::Done,
                default => null,
            };
        }
        if (array_key_exists('dateToFinish', $raw)) {
            $values['due'] = $this->utc($raw['dateToFinish']);
        }
        [$mapped, $issues] = $this->mapFields($values, $organization);

        $baseUrl = $this->baseUrls[(int) $organization->id] ??= (string) $this->connection((int) $organization->id)?->source_url;

        return new RemoteTask($id, $mapped, [
            'title' => trim((string) ($values['title'] ?? '')),
            'key' => '#' . $id,
            // `/tickets/showTicket` allein liefert nur das Modal-Fragment; Leantime verlinkt selbst so.
            'url' => $baseUrl !== '' ? $baseUrl . '/dashboard/home#/tickets/showTicket/' . $id : null,
            'project' => is_string($raw['projectName'] ?? null) && $raw['projectName'] !== '' ? $raw['projectName'] : null,
        ], $issues);
    }

    public function sourceUrl(ExternalReference $reference): ?string {
        $url = data_get($reference->payload, 'remote.url');
        $baseUrl = (string) $this->connection((int) $reference->organization_id)?->source_url;

        return is_string($url) && SelfHostedInstance::owns($baseUrl, $url) ? $url : null;
    }

    /** Leantime-Zeitpunkt („Y-m-d H:i:s“ in UTC) mit Zone; Leantimes Leerdaten und Leeres sind kein Zeitpunkt. */
    private function utc(mixed $value): ?string {
        if (! is_string($value) || trim($value) === '' || str_starts_with($value, '0000-00-00') || trim($value) === self::EPOCH_EMPTY) {
            return null;
        }

        return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', trim($value)) === 1 ? trim($value) . ' UTC' : $value;
    }
}
