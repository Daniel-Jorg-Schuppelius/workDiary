<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FreeScoutImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\FreeScout\Services;

use App\Enums\Task\TaskStatus;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\Organization;
use App\Plugins\FreeScout\Api\{FreeScoutClient, FreeScoutClientFactory};
use App\Plugins\FreeScout\FreeScoutPlugin;
use App\Plugins\FreeScout\Models\{FreeScoutConnection, FreeScoutProjectLink};
use App\Plugins\Support\TaskSync\{AbstractTaskImportService, IncompleteRemoteReadException, RemoteTask, SelfHostedInstance};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Database\Eloquent\Model;

/**
 * FreeScout-Adapter des Aufgabenimports (MVP-1336): Konversationen je
 * zugeordnetem Postfach — Erstlauf aktive und wartende, danach alle seit dem
 * Aufholpunkt geänderten (`updatedSince`), täglich alle. Status: active →
 * offen, pending → in Bearbeitung, closed und spam → erledigt; nur
 * veröffentlichte Konversationen (keine Entwürfe, kein Papierkorb).
 * Beschreibung ist die erste Nachricht; Fälligkeiten kennt FreeScout nicht.
 *
 * @extends AbstractTaskImportService<FreeScoutProjectLink, FreeScoutConnection>
 */
class FreeScoutImportService extends AbstractTaskImportService {
    /** @var array<int, string> Basis-URL je Organisation, einmal je Dienstinstanz gelesen */
    private array $baseUrls = [];

    public function __construct(private readonly FreeScoutClientFactory $clients) {}

    protected function pluginId(): string {
        return FreeScoutPlugin::ID;
    }

    protected function externalType(): string {
        return FreeScoutPlugin::EXT_TYPE_CONVERSATION;
    }

    protected function connectionModel(): string {
        return FreeScoutConnection::class;
    }

    protected function linkModel(): string {
        return FreeScoutProjectLink::class;
    }

    protected function isConfigured(int $organizationId): bool {
        return $this->clients->isConfigured($organizationId);
    }

    /**
     * @param  FreeScoutConnection  $connection
     * @param  FreeScoutProjectLink  $link
     * @return iterable<array<string, mixed>>
     */
    protected function fetch(Model $connection, Model $link, ?CarbonImmutable $since, bool $full): iterable {
        $statuses = ! $full && $since === null ? FreeScoutClient::OPEN_STATUSES : FreeScoutClient::ALL_STATUSES;
        $updatedSince = ! $full && $since !== null ? $since->utc()->format('Y-m-d\TH:i:s\Z') : null;
        foreach ($this->clients->for($link->organizationId())->conversations($link->sourceProjectId(), $statuses, $updatedSince) as $conversation) {
            if (is_array($conversation) && (string) ($conversation['mailboxId'] ?? '') === $link->sourceProjectId()) {
                yield $conversation;
            }
        }
    }

    /**
     * @param  FreeScoutConnection  $connection
     * @param  FreeScoutProjectLink  $link
     * @return array<string, mixed>|null
     */
    protected function fetchOne(Model $connection, Model $link, string $remoteId): ?array {
        $conversation = $this->clients->for($link->organizationId())->conversation($remoteId);

        return $conversation !== null && (string) ($conversation['mailboxId'] ?? '') === $link->sourceProjectId() ? $conversation : null;
    }

    /** @param array<string, mixed> $raw */
    protected function normalize(array $raw, Organization $organization): RemoteTask {
        $id = is_scalar($raw['id'] ?? null) ? (string) $raw['id'] : '';
        if (! ctype_digit($id)) {
            throw new IncompleteRemoteReadException('invalid_conversation');
        }

        $values = [];
        if (array_key_exists('subject', $raw)) {
            $values['title'] = is_string($raw['subject']) ? $raw['subject'] : null;
        }
        $first = $this->firstMessage($raw);
        if ($first !== null) {
            $values['description'] = StringHelper::htmlToText($first);
        }
        if (array_key_exists('status', $raw)) {
            $values['status'] = match ($raw['status']) {
                'active' => TaskStatus::Open,
                'pending' => TaskStatus::InProgress,
                'closed', 'spam' => TaskStatus::Done,
                default => null,
            };
        }
        [$mapped, $issues] = $this->mapFields($values, $organization);

        $baseUrl = $this->baseUrls[(int) $organization->id] ??= (string) $this->connection((int) $organization->id)?->source_url;

        return new RemoteTask($id, $mapped, [
            'title' => trim((string) ($values['title'] ?? '')),
            'key' => is_scalar($raw['number'] ?? null) ? '#' . $raw['number'] : null,
            'url' => $baseUrl !== '' ? $baseUrl . '/conversation/' . $id : null,
            'project' => null,
        ], $issues);
    }

    public function sourceUrl(ExternalReference $reference): ?string {
        $url = data_get($reference->payload, 'remote.url');
        $baseUrl = (string) $this->connection((int) $reference->organization_id)?->source_url;

        return is_string($url) && SelfHostedInstance::owns($baseUrl, $url) ? $url : null;
    }

    /**
     * Text der ältesten veröffentlichten Kunden-, Agenten- oder Chatnachricht;
     * Notizen und Entwürfe bleiben intern. Null, wenn es keine solche
     * Nachricht gibt oder die Antwort keine mitliefert (fehlend ≠ gelöscht).
     *
     * @param  array<string, mixed>  $raw
     */
    private function firstMessage(array $raw): ?string {
        $threads = data_get($raw, '_embedded.threads');
        if (! is_array($threads)) {
            return null;
        }
        $first = null;
        foreach ($threads as $thread) {
            if (is_array($thread) && in_array($thread['type'] ?? null, ['customer', 'message', 'chat'], true)
                && ($thread['state'] ?? 'published') === 'published' && is_int($thread['id'] ?? null)
                && ($first === null || $thread['id'] < $first['id'])) {
                $first = $thread;
            }
        }

        return $first !== null && is_string($first['body'] ?? null) ? $first['body'] : null;
    }
}
