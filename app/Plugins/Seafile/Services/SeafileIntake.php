<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SeafileIntake.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Seafile\Services;

use App\Enums\CloudIntake\CloudIntakeItemStatus;
use App\Models\CloudIntake\{CloudDocumentConnection, CloudDocumentItem, CloudDocumentRoute};
use App\Models\Document\DocumentVersion;
use App\Models\Integration\ExternalReference;
use App\Plugins\Seafile\Api\{SeafileClient, SeafileClientFactory};
use App\Plugins\Seafile\Exceptions\SeafileInstanceChangedException;
use App\Plugins\Seafile\{SeafileConfig, SeafilePlugin};
use App\Plugins\Support\Intake\{IntakeAccount, IntakeChangePage, IntakeContainer, IntakeItem};
use App\Plugins\Support\TaskSync\SelfHostedInstance;
use App\Services\CloudIntake\StaleCheckpointException;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\HashAlgorithm;
use CommonToolkit\Helper\Data\{CryptoHelper, JsonHelper};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Seafile als lesende Quelle des Cloud-Dokumenteingangs (MVP-1346/1347).
 *
 * Quelle ist eine Bibliothek, darin der Stammordner. Seafile-IDs sind
 * Inhalts-Hashes, auch für Ordner: Ein Zyklus beginnt mit dem Stammordner und
 * der Ordner-ID des letzten vollständigen Zyklus — stimmt sie, hat sich
 * darunter nichts geändert, und Seafile antwortet ohne Liste. Sonst folgt ein
 * budgetierter Ordnerscan wie bei Nextcloud; alle gefundenen Dateien gehen an
 * den Runner, der Bekanntes vor dem Download über die Revision überspringt.
 * Identität einer Datei ist ihre Datei-ID (Inhalts-Hash): Umbenennen und
 * Verschieben ändern sie nicht, eine inhaltlich geänderte Datei ist ein neuer
 * Beleg. Abgänge meldet der Zyklusabschluss.
 */
class SeafileIntake {
    public const EXT_TYPE_FILE = 'file';

    private const CHECKPOINT_VERSION = 1;

    /** Seafile vergibt allen leeren Dateien dieselbe ID. */
    private const EMPTY_FILE_ID = '0000000000000000000000000000000000000000';

    public function __construct(private readonly SeafileClientFactory $clients) {}

    public function account(CloudDocumentConnection $connection): IntakeAccount {
        $organizationId = (int) $connection->organization_id;
        $baseUrl = $this->clients->baseUrl($organizationId) ?? throw new RuntimeException('Seafile ist nicht eingerichtet.');
        $account = $this->clients->for($organizationId)->account();
        $email = $account['email'] ?? throw new RuntimeException('Seafile nennt kein Konto.');

        return new IntakeAccount(
            externalId: SelfHostedInstance::key($baseUrl) . ':' . $email,
            label: ($account['name'] ?? $account['contact'] ?? $email) . ' @ ' . SelfHostedInstance::name($baseUrl),
        );
    }

    /**
     * Unverschlüsselte Bibliotheken; verschlüsselte kann WorkDiary ohne Passwort nicht lesen.
     *
     * @return list<IntakeContainer>
     */
    public function containers(CloudDocumentConnection $connection, ?string $search): array {
        $needle = mb_strtolower(trim((string) $search));
        $containers = [];
        foreach ($this->client($connection)->libraries() as $library) {
            if (! $library['encrypted'] && ($needle === '' || str_contains(mb_strtolower($library['name']), $needle))) {
                $containers[] = new IntakeContainer($library['id'], $library['name'], (string) __('seafile::seafile.container.library'));
            }
        }

        return $containers;
    }

    public function changes(CloudDocumentConnection $connection, ?string $checkpoint): IntakeChangePage {
        $client = $this->client($connection);
        $repoId = $this->repoId($connection);
        $root = SeafileClient::path((string) ($connection->root_folder_path ?? '/'));
        $config = SeafileConfig::resolve((int) $connection->organization_id);
        $fingerprint = $this->fingerprint($connection);
        $state = $this->decode($checkpoint);
        if ($state['fingerprint'] !== $fingerprint) {
            $state = ['fingerprint' => $fingerprint, 'oid' => null, 'queue' => null, 'cycle_oid' => null, 'seen' => [], 'overflow' => false];
        }

        $listings = [];
        if ($state['queue'] === null) {
            $head = $client->folder($repoId, $root, $state['oid']);
            if ($head === null) {
                // Fehlender Stammordner ist ein Verbindungsfehler, nie „alles gelöscht“.
                throw new RuntimeException('Seafile-Stammordner nicht gefunden: ' . $root);
            }
            if ($head['uptodate']) {
                return new IntakeChangePage([], [], $this->encode($state));
            }
            $state = ['fingerprint' => $fingerprint, 'oid' => $state['oid'], 'queue' => [''], 'cycle_oid' => $head['oid'], 'seen' => [], 'overflow' => false];
            $listings[''] = $head['entries'];
        }

        $items = [];
        $seen = array_fill_keys($state['seen'], true);
        $queue = $state['queue'];
        $budget = $config['scan_folder_budget'];
        while ($budget-- > 0 && $queue !== []) {
            $relative = (string) array_shift($queue);
            $entries = $listings[$relative] ?? $client->folder($repoId, $this->join($root, $relative))['entries'] ?? null;
            if ($entries === null) {
                // 404 heißt auch „Bibliothek fehlt“ — dann wären alle Restordner leer und alles ein Abgang.
                if ($relative === '' || ! $client->libraryExists($repoId)) {
                    throw new RuntimeException('Seafile-Stammordner oder Bibliothek nicht gefunden: ' . $root);
                }

                continue; // Unterordner inzwischen entfernt
            }
            foreach ($entries as $entry) {
                $name = is_string($entry['name'] ?? null) ? $entry['name'] : '';
                if ($name === '' || str_contains($name, '/')) {
                    continue;
                }
                $path = $relative === '' ? $name : $relative . '/' . $name;
                if (($entry['type'] ?? null) === 'dir') {
                    $queue[] = $path;

                    continue;
                }
                $item = $this->item($entry, $path, $name);
                if ($item === null) {
                    continue;
                }
                if (! $state['overflow']) {
                    $seen[$item->itemId] = true;
                    if (count($seen) > $config['max_reconcile_files']) {
                        $state['overflow'] = true; // zu viele Dateien: kein Abgleich der Abgänge statt eines verkürzten
                        $seen = [];
                    }
                }
                $items[] = $item;
            }
        }

        $done = $queue === [];
        $tombstones = $done && ! $state['overflow'] ? $this->tombstones($connection, $seen) : [];
        $state = $done
            ? ['fingerprint' => $fingerprint, 'oid' => $state['cycle_oid'], 'queue' => null, 'cycle_oid' => null, 'seen' => [], 'overflow' => false]
            : ['fingerprint' => $fingerprint, 'oid' => $state['oid'], 'queue' => $queue, 'cycle_oid' => $state['cycle_oid'], 'seen' => array_keys($seen), 'overflow' => $state['overflow']];

        return new IntakeChangePage($items, $tombstones, $this->encode($state), hasMore: ! $done);
    }

    public function download(CloudDocumentConnection $connection, IntakeItem $item): StreamInterface {
        $root = SeafileClient::path((string) ($connection->root_folder_path ?? '/'));

        return $this->client($connection)->download($this->repoId($connection), $this->join($root, $item->path));
    }

    /** Herkunft am übernommenen Datensatz mit Link auf die Datei; eine neue Version zählt für ihr Dokument. */
    public function recordOrigin(CloudDocumentConnection $connection, IntakeItem $item, Model $imported): void {
        $subject = $imported instanceof DocumentVersion ? $imported->document : $imported;
        $instance = Str::before((string) $connection->external_account_id, ':');
        $baseUrl = $this->clients->baseUrl((int) $connection->organization_id);
        if (! $subject instanceof Model || $subject->getAttribute('organization_id') === null || $instance === '' || $baseUrl === null) {
            return;
        }
        $path = $this->join(SeafileClient::path((string) ($connection->root_folder_path ?? '/')), $item->path);
        $url = $baseUrl . '/lib/' . $this->repoId($connection) . '/file' . implode('/', array_map('rawurlencode', explode('/', $path)));

        ExternalReference::query()->updateOrCreate(
            ['plugin_id' => SeafilePlugin::ID, 'external_type' => self::EXT_TYPE_FILE, 'referenceable_type' => $subject->getMorphClass(), 'referenceable_id' => $subject->getKey()],
            ['organization_id' => $connection->organization_id, 'external_id' => $instance . ':' . $item->itemId, 'payload' => ['url' => $url], 'synced_at' => now()],
        );
    }

    /** Link zur Datei — nur, solange die Einstellungen auf dieselbe Instanz zeigen. */
    public function sourceUrl(ExternalReference $reference): ?string {
        $instance = Str::before((string) $reference->external_id, ':');
        $baseUrl = $reference->organization_id !== null ? $this->clients->baseUrl((int) $reference->organization_id) : null;
        $url = data_get($reference->payload, 'url');
        if ($baseUrl === null || ! is_string($url) || SelfHostedInstance::key($baseUrl) !== $instance) {
            return null;
        }

        return SelfHostedInstance::owns($baseUrl, $url) ? $url : null;
    }

    private function client(CloudDocumentConnection $connection): SeafileClient {
        $organizationId = (int) $connection->organization_id;
        $baseUrl = $this->clients->baseUrl($organizationId) ?? throw new RuntimeException('Seafile ist nicht eingerichtet.');
        $account = (string) $connection->external_account_id;
        if ($account !== '' && ! str_starts_with($account, SelfHostedInstance::key($baseUrl) . ':')) {
            throw new SeafileInstanceChangedException('Die Plugin-Einstellungen zeigen auf eine andere Seafile-Instanz als diese Verbindung.');
        }

        return $this->clients->for($organizationId);
    }

    private function repoId(CloudDocumentConnection $connection): string {
        $repoId = (string) $connection->container_id;
        if (! SeafileClient::isRepoId($repoId)) {
            throw new RuntimeException('Seafile-Bibliothek nicht gewählt oder ungültig.');
        }

        return $repoId;
    }

    /** @param array<string, mixed> $entry */
    private function item(array $entry, string $path, string $name): ?IntakeItem {
        $id = is_string($entry['id'] ?? null) ? strtolower($entry['id']) : '';
        if (preg_match('/^[0-9a-f]{40}$/', $id) !== 1 || $id === self::EMPTY_FILE_ID) {
            return null;
        }
        $mtime = $entry['mtime'] ?? null;

        return new IntakeItem(
            itemId: $id,
            path: $path,
            name: $name,
            revision: $id,
            size: is_int($entry['size'] ?? null) ? $entry['size'] : 0,
            modifiedAt: is_int($mtime) && $mtime > 0 ? CarbonImmutable::createFromTimestampUTC($mtime)->toIso8601String() : null,
            contentHash: $id,
        );
    }

    /**
     * Nicht mehr gesehene, noch nicht als entfernt markierte Dateien.
     *
     * @param  array<string, true>  $seen
     * @return list<string>
     */
    private function tombstones(CloudDocumentConnection $connection, array $seen): array {
        $ids = CloudDocumentItem::query()
            ->where('connection_id', $connection->id)
            ->where('status', '!=', CloudIntakeItemStatus::SourceGone->value)
            ->distinct()
            ->pluck('external_item_id')
            ->all();
        $tombstones = [];
        foreach ($ids as $id) {
            $id = (string) $id;
            if ($id !== '' && ! isset($seen[$id])) {
                $tombstones[] = $id;
            }
        }

        return $tombstones;
    }

    private function join(string $root, string $relative): string {
        return SeafileClient::path($root . '/' . $relative);
    }

    /** Bibliothek, Stammordner und aktive Regeln — ändert sich das, beginnt ein neuer Zyklus ohne Abkürzung. */
    private function fingerprint(CloudDocumentConnection $connection): string {
        $routes = $connection->routes()->where('active', true)->orderBy('id')->get(['id', 'updated_at'])
            ->map(static fn (CloudDocumentRoute $route): array => [$route->id, $route->updated_at?->format('U.u')])
            ->all();

        return (string) CryptoHelper::hash(JsonHelper::encode([$connection->container_id, $connection->root_folder_path, $routes]), HashAlgorithm::SHA256);
    }

    /**
     * @return array{fingerprint: string, oid: ?string, queue: ?list<string>, cycle_oid: ?string, seen: list<string>, overflow: bool}
     */
    private function decode(?string $checkpoint): array {
        if ($checkpoint === null || $checkpoint === '') {
            return ['fingerprint' => '', 'oid' => null, 'queue' => null, 'cycle_oid' => null, 'seen' => [], 'overflow' => false];
        }
        try {
            $data = JsonHelper::decode($checkpoint);
        } catch (InvalidArgumentException $e) {
            throw new StaleCheckpointException('Seafile-Checkpoint ungültig.', 0, $e);
        }
        if (! is_array($data) || ($data['v'] ?? null) !== self::CHECKPOINT_VERSION) {
            throw new StaleCheckpointException('Seafile-Checkpoint ungültig.');
        }
        $strings = static fn (mixed $list): array => is_array($list) ? array_values(array_filter($list, 'is_string')) : [];
        $id = static fn (mixed $value): ?string => is_string($value) && $value !== '' ? $value : null;

        return [
            'fingerprint' => is_string($data['fingerprint'] ?? null) ? $data['fingerprint'] : '',
            'oid' => $id($data['oid'] ?? null),
            'queue' => is_array($data['queue'] ?? null) ? $strings($data['queue']) : null,
            'cycle_oid' => $id($data['cycle_oid'] ?? null),
            'seen' => $strings($data['seen'] ?? null),
            'overflow' => ($data['overflow'] ?? false) === true,
        ];
    }

    /** @param array{fingerprint: string, oid: ?string, queue: ?list<string>, cycle_oid: ?string, seen: list<string>, overflow: bool} $state */
    private function encode(array $state): string {
        return JsonHelper::encode(['v' => self::CHECKPOINT_VERSION] + $state);
    }
}
