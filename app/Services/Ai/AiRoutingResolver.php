<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiRoutingResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Ai\{AiCapabilitySetting, AiProviderConnection};
use App\Models\Organization;
use App\Services\Ai\Dto\AiCapability;
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Licensing\ModuleStatusResolver;

/**
 * Routing Capability → Provider-Verbindung (Feature 025, MVP-399).
 *
 * Regelwerk in fester Reihenfolge: Modul-Gate (org-explizit, auch in
 * Jobs) → Capability-Opt-in der Organisation → erlaubte Verbindungen
 * (aktiv, gesund, Familie passt zum Verb) → Sensibilitäts-/Profil-Gate
 * (High bzw. Pflege-Profil: nur lokale Verbindungen — Cloud-Kandidaten
 * werden entfernt, nie stillschweigend ersetzt) → Nutzerwahl (nur wenn
 * erlaubt und Kandidat) → Reihenfolge = Fallback-Kette (Default zuerst,
 * dann konfigurierte Reihenfolge der erlaubten Verbindungen).
 */
class AiRoutingResolver {
    public function __construct(
        private readonly AiCapabilityRegistry $registry,
        private readonly ModuleStatusResolver $modules,
    ) {}

    public const MODULE = 'module.ai';

    /**
     * Geordnete Kandidatenliste (erste Verbindung = primäres Routing,
     * Rest = Fallback-Kette in Konfigurationsreihenfolge).
     *
     * @return list<AiProviderConnection>
     */
    public function resolveCandidates(
        Organization $organization,
        string $capabilityKey,
        ?int $requestedConnectionId = null,
    ): array {
        $capability = $this->registry->get($capabilityKey);

        if (! $this->modules->isActiveFor($organization, self::MODULE)) {
            throw AiUnavailableException::moduleInactive();
        }

        $setting = AiCapabilitySetting::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('capability', $capabilityKey)
            ->first();

        if ($setting === null || ! $setting->enabled) {
            throw AiUnavailableException::capabilityDisabled($capabilityKey);
        }

        $candidates = $this->allowedConnections($organization, $setting, $capability);

        if ($requestedConnectionId !== null) {
            if (! $setting->allow_user_choice) {
                throw AiUnavailableException::connectionNotAllowed($requestedConnectionId);
            }

            $candidates = $this->moveToFront($candidates, $requestedConnectionId);
        }

        if ($candidates === []) {
            // Fällt genau eine zugelassene Verbindung wegen eines
            // hinterlegten Fehlers aus, ist DAS die Ursache — sie gehört in
            // die Meldung, statt auf die Capability zu zeigen.
            $blocked = $this->onlyBlockedConnection($organization, $setting);

            throw AiUnavailableException::noConnection(
                $capabilityKey,
                $blocked?->name,
                $blocked?->last_error,
            );
        }

        return $candidates;
    }

    public function resolve(
        Organization $organization,
        string $capabilityKey,
        ?int $requestedConnectionId = null,
    ): AiProviderConnection {
        return $this->resolveCandidates($organization, $capabilityKey, $requestedConnectionId)[0];
    }

    /**
     * Cloud erlaubt? Nein bei hoher Sensibilität (Matrix in config/ai.php)
     * oder gesperrtem Branchenprofil (Pflege: Art. 9 DSGVO/§ 203 StGB) —
     * geprüft gegen das zuletzt installierte Profil UND alle jemals
     * installierten Profilversionen.
     */
    public function cloudAllowed(Organization $organization, AiCapability $capability): bool {
        $allowed = (array) config('ai.cloud_allowed_sensitivities', []);
        if (! in_array($capability->sensitivity->value, $allowed, true)) {
            return false;
        }

        $settings = is_array($organization->settings) ? $organization->settings : [];
        foreach ((array) config('ai.cloud_blocked_profiles', []) as $profileCode) {
            if (($settings['branch_profile_code'] ?? null) === $profileCode) {
                return false;
            }
            if (data_get($settings, 'branch_profile_versions.' . $profileCode) !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Genau eine zugelassene Verbindung, die wegen eines Verbindungsfehlers
     * ausfällt — sonst null (bei mehreren Kandidaten oder anderen Gründen wie
     * Cloud-Sperre bleibt die allgemeine Meldung richtig).
     */
    private function onlyBlockedConnection(Organization $organization, AiCapabilitySetting $setting): ?AiProviderConnection {
        $allowedIds = array_map('intval', (array) ($setting->allowed_connection_ids ?? []));
        if (count($allowedIds) !== 1) {
            return null;
        }

        $connection = AiProviderConnection::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereKey($allowedIds[0])
            ->first();

        if (! $connection instanceof AiProviderConnection || $connection->isRunnable()) {
            return null;
        }

        return $connection;
    }

    /** @return list<AiProviderConnection> */
    private function allowedConnections(
        Organization $organization,
        AiCapabilitySetting $setting,
        AiCapability $capability,
    ): array {
        $allowedIds = array_map('intval', (array) ($setting->allowed_connection_ids ?? []));

        if ($allowedIds === []) {
            return [];
        }

        $connections = AiProviderConnection::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $allowedIds)
            ->get()
            ->keyBy('id');

        $cloudAllowed = $this->cloudAllowed($organization, $capability);

        // Konfigurierte Reihenfolge der erlaubten IDs beibehalten,
        // Default-Verbindung an den Anfang.
        $orderedIds = $allowedIds;
        if ($setting->default_connection_id !== null) {
            $orderedIds = $this->frontloaded($orderedIds, (int) $setting->default_connection_id);
        }

        $result = [];
        foreach ($orderedIds as $id) {
            /** @var AiProviderConnection|null $connection */
            $connection = $connections->get($id);
            if ($connection === null || ! $connection->isRunnable()) {
                continue;
            }
            if (! $connection->supportsVerb($capability->verb)) {
                continue;
            }
            if (! $cloudAllowed && $connection->isCloud()) {
                continue;
            }
            $result[] = $connection;
        }

        return $result;
    }

    /**
     * @param list<AiProviderConnection> $candidates
     * @return list<AiProviderConnection>
     */
    private function moveToFront(array $candidates, int $connectionId): array {
        $front = array_values(array_filter(
            $candidates,
            static fn (AiProviderConnection $c): bool => (int) $c->id === $connectionId
        ));

        if ($front === []) {
            throw AiUnavailableException::connectionNotAllowed($connectionId);
        }

        $rest = array_values(array_filter(
            $candidates,
            static fn (AiProviderConnection $c): bool => (int) $c->id !== $connectionId
        ));

        return [...$front, ...$rest];
    }

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    private function frontloaded(array $ids, int $first): array {
        if (! in_array($first, $ids, true)) {
            return $ids;
        }

        return [$first, ...array_values(array_filter($ids, static fn (int $id): bool => $id !== $first))];
    }
}
