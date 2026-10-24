<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaperlessIntake.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Paperless\Services;

use App\Enums\CloudIntake\CloudIntakeItemStatus;
use App\Models\CloudIntake\{CloudDocumentConnection, CloudDocumentItem, CloudDocumentRoute};
use App\Models\Document\DocumentVersion;
use App\Models\Integration\ExternalReference;
use App\Plugins\Paperless\Api\{PaperlessClient, PaperlessClientFactory};
use App\Plugins\Paperless\Exceptions\PaperlessInstanceChangedException;
use App\Plugins\Paperless\{PaperlessConfig, PaperlessPlugin};
use App\Plugins\Support\Intake\{IntakeAccount, IntakeChangePage, IntakeContainer, IntakeItem};
use App\Plugins\Support\TaskSync\SelfHostedInstance;
use App\Services\CloudIntake\StaleCheckpointException;
use CommonToolkit\Enums\HashAlgorithm;
use CommonToolkit\Helper\Data\{CryptoHelper, JsonHelper};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Paperless-ngx als lesende Quelle des Cloud-Dokumenteingangs (MVP-1315/1316).
 *
 * Ein Zyklus beginnt mit der vollständigen ID-Liste der gewählten Quelle:
 * daraus folgen neue Mitglieder (auch nachträglich per Massenbearbeitung
 * getaggte — die ändert `modified` nicht) und Abgänge (Tombstones). Bekannte
 * Dokumente kommen nur mit `modified` nach dem Zyklusstart des Vorlaufs
 * erneut, ihre Revision ist die Prüfsumme des Originals. Der Checkpoint führt
 * die Warteschlange des laufenden Zyklus und die bereits ausgewerteten IDs;
 * ändern sich Quelle, Stammordner oder Regeln, wird alles neu ausgewertet.
 */
class PaperlessIntake {
    public const EXT_TYPE_DOCUMENT = 'document';

    private const CHECKPOINT_VERSION = 1;

    /** Container-Präfix → Filter der Dokumentliste. */
    private const FILTERS = ['tag' => 'tags__id__all', 'correspondent' => 'correspondent__id', 'type' => 'document_type__id'];

    public function __construct(private readonly PaperlessClientFactory $clients) {}

    public function account(CloudDocumentConnection $connection): IntakeAccount {
        $organizationId = (int) $connection->organization_id;
        $baseUrl = $this->clients->baseUrl($organizationId) ?? throw new RuntimeException('Paperless-ngx ist nicht eingerichtet.');
        $user = $this->clients->for($organizationId)->currentUser();

        return new IntakeAccount(
            externalId: SelfHostedInstance::key($baseUrl) . ':' . $user['id'],
            label: $user['username'] . ' @ ' . SelfHostedInstance::name($baseUrl),
        );
    }

    /** @return list<IntakeContainer> */
    public function containers(CloudDocumentConnection $connection, ?string $search): array {
        $containers = [new IntakeContainer('all', (string) __('paperless::paperless.container.all'))];
        foreach ($this->client($connection)->containerOptions($search) as $option) {
            $containers[] = new IntakeContainer($option['kind'] . ':' . $option['id'], $option['name'], (string) __('paperless::paperless.container.kind.' . $option['kind']));
        }

        return $containers;
    }

    public function changes(CloudDocumentConnection $connection, ?string $checkpoint): IntakeChangePage {
        $client = $this->client($connection);
        $filter = $this->filter($connection->container_id);
        $state = $this->decode($checkpoint);
        $fingerprint = $this->fingerprint($connection);
        if ($state['fingerprint'] !== $fingerprint) {
            $state['seen'] = [];
            $state['fingerprint'] = $fingerprint;
        }

        $tombstones = [];
        if ($state['queue'] === null) {
            $newest = $client->newestModified($filter);
            $members = $client->documentIds($filter);
            [$known, $live] = $this->knownItems($connection);
            $tombstones = array_values(array_diff($live, array_map('strval', $members)));
            if ($tombstones !== []) {
                // Gleiche Gesamtzahl trotz gleichzeitigem Löschen und Anlegen kann ein Mitglied beim Blättern
                // überspringen; als Abgang zählt nur, was die Quelle auf direkte Nachfrage nicht mehr führt.
                $stillMembers = array_map('strval', $client->documentIds($filter + ['id__in' => implode(',', $tombstones)]));
                $tombstones = array_values(array_diff($tombstones, $stillMembers));
            }

            $memberSet = array_flip($members);
            $seen = array_values(array_filter($state['seen'], static fn (int $id): bool => isset($memberSet[$id]) && ! isset($known[(string) $id])));
            $seenSet = array_flip($seen);
            $changed = $state['since'] !== null ? $client->documentIds($filter + ['modified__gt' => $state['since']]) : [];
            $fresh = array_filter($members, static fn (int $id): bool => ! isset($known[(string) $id]) && ! isset($seenSet[$id]));
            $queue = array_values(array_unique([...$fresh, ...array_filter($changed, static fn (int $id): bool => isset($memberSet[$id]))]));
            sort($queue);

            $state = ['since' => $state['since'], 'fingerprint' => $fingerprint, 'seen' => $seen, 'queue' => $queue, 'next' => $newest ?? $state['since']];
        }

        $batch = array_splice($state['queue'], 0, PaperlessConfig::resolve((int) $connection->organization_id)['page_size']);
        $items = $this->items($client, $connection, $batch);
        $state['seen'] = [...$state['seen'], ...$batch];
        if ($state['queue'] === []) {
            $state = ['since' => $state['next'], 'fingerprint' => $fingerprint, 'seen' => $state['seen'], 'queue' => null, 'next' => null];
        }

        return new IntakeChangePage($items, $tombstones, $this->encode($state), hasMore: $state['queue'] !== null);
    }

    public function download(CloudDocumentConnection $connection, IntakeItem $item): StreamInterface {
        if (! ctype_digit($item->itemId)) {
            throw new RuntimeException('Paperless-Dokument-ID ungültig.');
        }

        return $this->client($connection)->download((int) $item->itemId);
    }

    /** Herkunft am übernommenen Datensatz; eine neue Version zählt für ihr Dokument. */
    public function recordOrigin(CloudDocumentConnection $connection, IntakeItem $item, Model $imported): void {
        $subject = $imported instanceof DocumentVersion ? $imported->document : $imported;
        $instance = Str::before((string) $connection->external_account_id, ':');
        if (! $subject instanceof Model || $subject->getAttribute('organization_id') === null || $instance === '') {
            return;
        }

        ExternalReference::query()->updateOrCreate(
            ['plugin_id' => PaperlessPlugin::ID, 'external_type' => self::EXT_TYPE_DOCUMENT, 'referenceable_type' => $subject->getMorphClass(), 'referenceable_id' => $subject->getKey()],
            ['organization_id' => $connection->organization_id, 'external_id' => $instance . ':' . $item->itemId, 'synced_at' => now()],
        );
    }

    /** Link zum Original — nur, solange die Einstellungen auf dieselbe Instanz zeigen. */
    public function sourceUrl(ExternalReference $reference): ?string {
        [$instance, $documentId] = array_pad(explode(':', (string) $reference->external_id, 2), 2, '');
        $baseUrl = $reference->organization_id !== null ? $this->clients->baseUrl((int) $reference->organization_id) : null;
        if ($baseUrl === null || ! ctype_digit($documentId) || SelfHostedInstance::key($baseUrl) !== $instance) {
            return null;
        }

        return $baseUrl . '/documents/' . $documentId . '/details';
    }

    private function client(CloudDocumentConnection $connection): PaperlessClient {
        $organizationId = (int) $connection->organization_id;
        $baseUrl = $this->clients->baseUrl($organizationId) ?? throw new RuntimeException('Paperless-ngx ist nicht eingerichtet.');
        $account = (string) $connection->external_account_id;
        if ($account !== '' && ! str_starts_with($account, SelfHostedInstance::key($baseUrl) . ':')) {
            throw new PaperlessInstanceChangedException('Die Plugin-Einstellungen zeigen auf eine andere Paperless-Instanz als diese Verbindung.');
        }

        return $this->clients->for($organizationId);
    }

    /** @return array<string, string> */
    private function filter(?string $containerId): array {
        if ($containerId === 'all') {
            return [];
        }
        if (preg_match('/^(tag|correspondent|type):(\d+)$/', (string) $containerId, $m) !== 1) {
            throw new RuntimeException('Paperless-Quelle nicht gewählt oder ungültig.');
        }

        return [self::FILTERS[$m[1]] => $m[2]];
    }

    /**
     * Alle bisher gesehenen IDs (jede Statusart) und die nicht als entfernt markierten.
     *
     * @return array{0: array<int|string, true>, 1: list<string>}
     */
    private function knownItems(CloudDocumentConnection $connection): array {
        $known = [];
        $live = [];
        CloudDocumentItem::query()
            ->where('connection_id', $connection->id)
            ->get(['external_item_id', 'status'])
            ->each(function (CloudDocumentItem $item) use (&$known, &$live): void {
                $known[$item->external_item_id] = true;
                if ($item->status !== CloudIntakeItemStatus::SourceGone) {
                    $live[$item->external_item_id] = true;
                }
            });

        return [$known, array_map('strval', array_keys($live))];
    }

    /**
     * @param  list<int>  $ids
     * @return list<IntakeItem>
     */
    private function items(PaperlessClient $client, CloudDocumentConnection $connection, array $ids): array {
        $documents = $client->documents($ids);
        $correspondents = $client->names('correspondents', $this->references($documents, 'correspondent'));
        $types = $client->names('document_types', $this->references($documents, 'document_type'));
        $root = trim((string) $connection->root_folder_path, '/');

        $items = [];
        foreach ($ids as $id) {
            $document = $documents[$id] ?? null;
            if ($document === null) {
                continue; // inzwischen gelöscht — der nächste Zyklus meldet den Abgang
            }
            $folder = $this->segment($types[$document['document_type'] ?? 0] ?? null) . '/' . $this->segment($correspondents[$document['correspondent'] ?? 0] ?? null);
            $relative = $this->relativeTo($root, $folder);
            if ($relative === null) {
                continue;
            }
            $metadata = $client->metadata($id);
            if ($metadata === null) {
                continue;
            }
            $name = $this->fileName($document, $metadata, $id);
            $checksum = is_string($metadata['original_checksum'] ?? null) ? $metadata['original_checksum'] : '';

            $items[] = new IntakeItem(
                itemId: (string) $id,
                path: ($relative === '' ? '' : $relative . '/') . $name,
                name: $name,
                revision: $checksum !== '' ? $checksum : 'document-' . $id,
                size: is_int($metadata['original_size'] ?? null) ? $metadata['original_size'] : 0,
                mime: is_string($metadata['original_mime_type'] ?? null) ? $metadata['original_mime_type'] : null,
                modifiedAt: is_string($document['modified'] ?? null) ? $document['modified'] : null,
                contentHash: $checksum !== '' ? $checksum : null,
            );
        }

        return $items;
    }

    /**
     * @param  array<int, array<string, mixed>>  $documents
     * @return list<int>
     */
    private function references(array $documents, string $field): array {
        $ids = [];
        foreach ($documents as $document) {
            if (is_int($document[$field] ?? null)) {
                $ids[$document[$field]] = $document[$field];
            }
        }

        return array_values($ids);
    }

    /** Pfadsegment aus einem Namen; ohne Zuordnung „-“. */
    private function segment(?string $name): string {
        $segment = trim(str_replace(['/', '\\'], '-', (string) $name));

        return $segment !== '' ? $segment : '-';
    }

    /** Pfad unterhalb des Stammordners (`Dokumenttyp/Korrespondent`); null außerhalb. */
    private function relativeTo(string $root, string $folder): ?string {
        if ($root === '') {
            return $folder;
        }
        $lowerRoot = mb_strtolower($root);
        $lowerFolder = mb_strtolower($folder);
        if ($lowerFolder === $lowerRoot) {
            return '';
        }

        return str_starts_with($lowerFolder . '/', $lowerRoot . '/') ? mb_substr($folder, mb_strlen($root) + 1) : null;
    }

    /**
     * Ursprünglicher Dateiname; ältere Dokumente ohne ihn heißen nach dem
     * Titel mit der Endung der abgelegten Datei.
     *
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $metadata
     */
    private function fileName(array $document, array $metadata, int $id): string {
        // Ab Paperless 3 gehört die Datei zur neuesten Version; `original_file_name` der Liste ist der der ersten.
        $original = $metadata['original_filename'] ?? $document['original_file_name'] ?? null;
        $name = is_string($original) ? trim(Str::afterLast(str_replace('\\', '/', $original), '/')) : '';
        if ($name !== '') {
            return $name;
        }
        $title = $this->segment(is_string($document['title'] ?? null) ? $document['title'] : 'document-' . $id);
        $stored = is_string($metadata['media_filename'] ?? null) ? $metadata['media_filename'] : '';
        $extension = str_contains($stored, '.') ? Str::afterLast($stored, '.') : '';

        return $extension !== '' ? $title . '.' . $extension : $title;
    }

    /** Quelle, Stammordner und aktive Regeln — ändert sich das, wird neu ausgewertet. */
    private function fingerprint(CloudDocumentConnection $connection): string {
        $routes = $connection->routes()->where('active', true)->orderBy('id')->get(['id', 'updated_at'])
            ->map(static fn (CloudDocumentRoute $route): array => [$route->id, $route->updated_at?->format('U.u')])
            ->all();

        return (string) CryptoHelper::hash(JsonHelper::encode([$connection->container_id, $connection->root_folder_path, $routes]), HashAlgorithm::SHA256);
    }

    /**
     * @return array{since: ?string, fingerprint: string, seen: list<int>, queue: ?list<int>, next: ?string}
     */
    private function decode(?string $checkpoint): array {
        if ($checkpoint === null || $checkpoint === '') {
            return ['since' => null, 'fingerprint' => '', 'seen' => [], 'queue' => null, 'next' => null];
        }
        try {
            $data = JsonHelper::decode($checkpoint);
        } catch (InvalidArgumentException $e) {
            throw new StaleCheckpointException('Paperless-Checkpoint ungültig.', 0, $e);
        }
        if (! is_array($data) || ($data['v'] ?? null) !== self::CHECKPOINT_VERSION) {
            throw new StaleCheckpointException('Paperless-Checkpoint ungültig.');
        }
        $ids = static fn (mixed $list): array => is_array($list) ? array_values(array_filter($list, 'is_int')) : [];
        $timestamp = static fn (mixed $value): ?string => is_string($value) && $value !== '' ? $value : null;

        return [
            'since' => $timestamp($data['since'] ?? null),
            'fingerprint' => is_string($data['fingerprint'] ?? null) ? $data['fingerprint'] : '',
            'seen' => $ids($data['seen'] ?? null),
            'queue' => is_array($data['queue'] ?? null) ? $ids($data['queue']) : null,
            'next' => $timestamp($data['next'] ?? null),
        ];
    }

    /** @param array{since: ?string, fingerprint: string, seen: list<int>, queue: ?list<int>, next: ?string} $state */
    private function encode(array $state): string {
        return JsonHelper::encode(['v' => self::CHECKPOINT_VERSION] + $state);
    }
}
