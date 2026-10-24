<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GlpiImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Glpi\Services;

use App\Enums\Task\TaskStatus;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\Organization;
use App\Plugins\Glpi\Api\{GlpiClient, GlpiClientFactory};
use App\Plugins\Glpi\GlpiPlugin;
use App\Plugins\Glpi\Models\{GlpiConnection, GlpiProjectLink};
use App\Plugins\Support\TaskSync\{AbstractTaskImportService, IncompleteRemoteReadException, RemoteTask, SelfHostedInstance};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Database\Eloquent\Model;

/**
 * GLPI-Adapter des Aufgabenimports (MVP-1337): Tickets je zugeordneter
 * Entität. Status aus der Statusnummer: 1 neu und 10 Genehmigung (GLPI 11) →
 * offen, 2 zugewiesen, 3 geplant und 4 wartend → in Bearbeitung, 5 gelöst
 * und 6 geschlossen → erledigt. GLPI liefert Zeitpunkte ohne Zone in der
 * Zeitzone des API-Kontos: die Lösungsfrist gilt als Datum, der Folgelauf
 * liest nach Änderung absteigend mit 14 Stunden Spielraum (größter
 * Zonenabstand) — ein Ticket zu viel wird nur verglichen, keines geht
 * verloren.
 *
 * @extends AbstractTaskImportService<GlpiProjectLink, GlpiConnection>
 */
class GlpiImportService extends AbstractTaskImportService {
    private const ZONE_MARGIN_HOURS = 14;

    /** @var array<int, string> Basis-URL je Organisation, einmal je Dienstinstanz gelesen */
    private array $baseUrls = [];

    public function __construct(private readonly GlpiClientFactory $clients) {}

    protected function pluginId(): string {
        return GlpiPlugin::ID;
    }

    protected function externalType(): string {
        return GlpiPlugin::EXT_TYPE_TICKET;
    }

    protected function connectionModel(): string {
        return GlpiConnection::class;
    }

    protected function linkModel(): string {
        return GlpiProjectLink::class;
    }

    protected function isConfigured(int $organizationId): bool {
        return $this->clients->isConfigured($organizationId);
    }

    /**
     * @param  GlpiConnection  $connection
     * @param  GlpiProjectLink  $link
     * @return iterable<array<string, mixed>>
     */
    protected function fetch(Model $connection, Model $link, ?CarbonImmutable $since, bool $full): iterable {
        $changedAfter = $full ? null : $since?->subHours(self::ZONE_MARGIN_HOURS);
        foreach ($this->clients->for($link->organizationId())->tickets($link->sourceProjectId(), $changedAfter?->utc()->format('Y-m-d H:i:s')) as $ticket) {
            if (! is_array($ticket) || (string) ($ticket['entities_id'] ?? '') !== $link->sourceProjectId()) {
                continue;
            }
            if (! $full && $since === null && in_array((int) ($ticket['status'] ?? 0), [5, 6], true)) {
                continue;
            }
            if ($changedAfter !== null && ! $this->changedSince($ticket['date_mod'] ?? null, $changedAfter)) {
                continue;
            }
            yield $ticket;
        }
    }

    /**
     * @param  GlpiConnection  $connection
     * @param  GlpiProjectLink  $link
     * @return array<string, mixed>|null
     */
    protected function fetchOne(Model $connection, Model $link, string $remoteId): ?array {
        $ticket = $this->clients->for($link->organizationId())->ticket($remoteId, $link->sourceProjectId());

        return $ticket !== null && (string) ($ticket['entities_id'] ?? '') === $link->sourceProjectId() && ! in_array($ticket['is_deleted'] ?? 0, [1, '1', true], true) ? $ticket : null;
    }

    /** @param array<string, mixed> $raw */
    protected function normalize(array $raw, Organization $organization): RemoteTask {
        $id = is_scalar($raw['id'] ?? null) ? (string) $raw['id'] : '';
        if (! ctype_digit($id)) {
            throw new IncompleteRemoteReadException('invalid_ticket');
        }

        $values = [];
        if (array_key_exists('name', $raw)) {
            $values['title'] = is_string($raw['name']) ? GlpiClient::unmask($raw['name']) : null;
        }
        if (array_key_exists('content', $raw)) {
            $values['description'] = is_string($raw['content']) ? StringHelper::htmlToText($this->unmasked($raw['content'])) : null;
        }
        if (array_key_exists('status', $raw)) {
            $values['status'] = match ((int) $raw['status']) {
                1, 10 => TaskStatus::Open,
                2, 3, 4 => TaskStatus::InProgress,
                5, 6 => TaskStatus::Done,
                default => null,
            };
        }
        if (array_key_exists('time_to_resolve', $raw)) {
            $values['due_on'] = is_string($raw['time_to_resolve']) ? substr($raw['time_to_resolve'], 0, 10) : null;
        }
        [$mapped, $issues] = $this->mapFields($values, $organization);

        $baseUrl = $this->baseUrls[(int) $organization->id] ??= (string) $this->connection((int) $organization->id)?->source_url;

        return new RemoteTask($id, $mapped, [
            'title' => trim((string) ($values['title'] ?? '')),
            'key' => '#' . $id,
            'url' => $baseUrl !== '' ? $baseUrl . '/front/ticket.form.php?id=' . $id : null,
            'project' => null,
        ], $issues);
    }

    public function sourceUrl(ExternalReference $reference): ?string {
        $url = data_get($reference->payload, 'remote.url');
        $baseUrl = (string) $this->connection((int) $reference->organization_id)?->source_url;

        return is_string($url) && SelfHostedInstance::owns($baseUrl, $url) ? $url : null;
    }

    /**
     * Ältere GLPI-Versionen ignorieren `X-GLPI-Sanitized-Content` und liefern
     * maskiertes HTML (`&#60;p&#62;`) — dann erst die Entities auflösen.
     */
    private function unmasked(string $content): string {
        return ! str_contains($content, '<') && preg_match('/&(lt|#60|#x3c);/i', $content) === 1 ? StringHelper::htmlEntitiesToText($content) : $content;
    }
}
