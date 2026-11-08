<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbstractCloudIntakeSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Intake;

use App\Models\CloudIntake\{CloudDocumentConnection, CloudDocumentRoute};
use App\Plugins\Support\TaskSync\SelfHostedInstance;
use CommonToolkit\Enums\HashAlgorithm;
use CommonToolkit\Helper\Data\{CryptoHelper, JsonHelper};
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Rahmen der selbst gehosteten Quellen des Cloud-Dokumenteingangs mit
 * API-Token (Paperless-ngx, Seafile; Konsolidierung kon-09): der Client
 * kommt nur, solange die Einstellungen auf die verbundene Instanz zeigen;
 * der Fingerabdruck aus Quelle, Stammordner und aktiven Regeln entscheidet
 * den Zyklusneustart; der Checkpoint ist versioniertes JSON, dessen Felder
 * die Quelle selbst deutet ({@see stateFrom()}).
 *
 * @template TClient of object
 * @template TState of array<string, mixed>
 */
abstract class AbstractCloudIntakeSource {
    protected const int CHECKPOINT_VERSION = 1;

    /** Name der Quelle in Fehlermeldungen. */
    abstract protected function sourceName(): string;

    /** Basis-URL der eingestellten Instanz; null, wenn nicht eingerichtet. */
    abstract protected function configuredBaseUrl(int $organizationId): ?string;

    /** @return TClient */
    abstract protected function clientFor(int $organizationId): object;

    /** Die Einstellungen zeigen auf eine andere Instanz als die Verbindung. */
    abstract protected function instanceChanged(): RuntimeException;

    /**
     * Checkpoint unlesbar oder von einer anderen Version — die Quelle liefert die
     * `StaleCheckpointException` des Eingangs, auf die der Runner mit einem
     * begrenzten Vollabgleich antwortet (Plattform greift nicht ins Modul).
     */
    abstract protected function invalidCheckpoint(?Throwable $cause = null): RuntimeException;

    /** @return TState */
    abstract protected function emptyState(): array;

    /**
     * Felder eines gelesenen Checkpoints typtreu deuten; Unlesbares fällt auf den Leerwert.
     *
     * @param  array<mixed>  $data
     * @return TState
     */
    abstract protected function stateFrom(array $data): array;

    /** @return TClient */
    protected function client(CloudDocumentConnection $connection): object {
        $organizationId = (int) $connection->organization_id;
        $baseUrl = $this->requireBaseUrl($organizationId);
        $account = (string) $connection->external_account_id;
        if ($account !== '' && ! str_starts_with($account, SelfHostedInstance::key($baseUrl) . ':')) {
            throw $this->instanceChanged();
        }

        return $this->clientFor($organizationId);
    }

    protected function requireBaseUrl(int $organizationId): string {
        return $this->configuredBaseUrl($organizationId) ?? throw new RuntimeException($this->sourceName() . ' ist nicht eingerichtet.');
    }

    /** Quelle, Stammordner und aktive Regeln — ändert sich das, beginnt ein Zyklus ohne Abkürzung neu. */
    protected function fingerprint(CloudDocumentConnection $connection): string {
        $routes = $connection->routes()->where('active', true)->orderBy('id')->get(['id', 'updated_at'])
            ->map(static fn (CloudDocumentRoute $route): array => [$route->id, $route->updated_at?->format('U.u')])
            ->all();

        return (string) CryptoHelper::hash(JsonHelper::encode([$connection->container_id, $connection->root_folder_path, $routes]), HashAlgorithm::SHA256);
    }

    /** @return TState */
    protected function decode(?string $checkpoint): array {
        if ($checkpoint === null || $checkpoint === '') {
            return $this->emptyState();
        }
        try {
            $data = JsonHelper::decode($checkpoint);
        } catch (InvalidArgumentException $e) {
            throw $this->invalidCheckpoint($e);
        }
        if (! is_array($data) || ($data['v'] ?? null) !== static::CHECKPOINT_VERSION) {
            throw $this->invalidCheckpoint();
        }

        return $this->stateFrom($data);
    }

    /** @param TState $state */
    protected function encode(array $state): string {
        return JsonHelper::encode(['v' => static::CHECKPOINT_VERSION] + $state);
    }
}
