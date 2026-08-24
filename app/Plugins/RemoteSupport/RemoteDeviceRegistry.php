<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteDeviceRegistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\RemoteSupport;

use App\Models\{Asset, ExternalReference, ExternalReferenceAlias, RemotePendingSession};
use App\Plugins\RemoteSupport\Providers\{AnyDeskClient, TeamViewerClient};

/**
 * Geräte-Identität des Fernwartungs-Plugins: pflegt die AnyDesk-/TeamViewer-IDs
 * am Asset über die external_references-Tabelle ({@see setRemoteId()} /
 * {@see forgetRemoteId()}) und überführt bei Duplikaten IDs + Pending-Sitzungen
 * auf das Zielgerät ({@see mergeRemoteDevice()}).
 */
class RemoteDeviceRegistry {
    /** Provider-Kennung → external_type des Geräte-Links. */
    public const DEVICE_TYPES = [
        AnyDeskClient::ID => 'anydesk_id',
        TeamViewerClient::ID => 'teamviewer_id',
    ];

    /**
     * Asset-Unterkategorien (category_code), die eine Fernwartungs-ID tragen
     * können. Nur für diese Geräte wird das Panel angeboten und nur ihnen lassen
     * sich offene Verbindungen zuweisen (AnyDesk/TeamViewer gibt es auch für
     * Tablets und Smartphones).
     *
     * @var list<string>
     */
    public const REMOTE_CATEGORY_CODES = ['workstation', 'server', 'notebook', 'tablet', 'smartphone'];

    /**
     * Hinterlegt eine Geräte-ID additiv: ein Gerät kann mehrere IDs je
     * Anbieter tragen (Neuinstallation → neue AnyDesk-ID, Zweitinstanz).
     * `extref_unique` erlaubt je Gerät nur EINE Primär-Referenz je Typ —
     * weitere IDs laufen als {@see ExternalReferenceAlias} (gleiches Muster
     * wie bei Session-Referenzen). Eine ID zeigt immer auf genau ein Gerät.
     */
    public function setRemoteId(Asset $asset, string $provider, string $remoteId): void {
        $remoteId = trim($remoteId);
        if ($remoteId === '') {
            return;
        }

        $primary = ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('plugin_id', RemoteSupportPlugin::ID)
            ->where('external_type', self::deviceType($provider))
            ->forReferenceable($asset)
            ->first();

        if ($primary === null) {
            ExternalReference::query()->withoutGlobalScopes()->create([
                'organization_id' => $asset->organization_id,
                'plugin_id' => RemoteSupportPlugin::ID,
                'external_type' => self::deviceType($provider),
                'external_id' => $remoteId,
                'referenceable_type' => $asset->getMorphClass(),
                'referenceable_id' => $asset->getKey(),
                'synced_at' => now(),
            ]);

            // Die ID ist jetzt Primär-Referenz — ein etwaiger Alias derselben
            // ID (früheres Ziel) wäre widersprüchlich.
            $this->deviceAliasQuery($asset->organization_id, $provider)
                ->where('external_id', $remoteId)
                ->delete();

            return;
        }

        if ((string) $primary->external_id === $remoteId) {
            $primary->update(['synced_at' => now()]);

            return;
        }

        ExternalReferenceAlias::query()->withoutGlobalScopes()->updateOrCreate(
            [
                'organization_id' => $asset->organization_id,
                'plugin_id' => RemoteSupportPlugin::ID,
                'external_type' => self::deviceType($provider),
                'external_id' => $remoteId,
            ],
            [
                'referenceable_type' => $asset->getMorphClass(),
                'referenceable_id' => $asset->getKey(),
            ],
        );
    }

    /**
     * Basis-Query für Geräte-ID-Aliasse eines Anbieters (mandanten-gefiltert).
     *
     * @return \Illuminate\Database\Eloquent\Builder<ExternalReferenceAlias>
     */
    private function deviceAliasQuery(?int $organizationId, string $provider): \Illuminate\Database\Eloquent\Builder {
        return ExternalReferenceAlias::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', RemoteSupportPlugin::ID)
            ->where('external_type', self::deviceType($provider));
    }

    /** Entfernt eine bestimmte Geräte-ID — ohne $remoteId alle IDs des Anbieters. */
    public function forgetRemoteId(Asset $asset, string $provider, ?string $remoteId = null): void {
        ExternalReference::query()
            ->forPlugin($asset->organization_id, RemoteSupportPlugin::ID, self::deviceType($provider))
            ->forReferenceable($asset)
            ->when($remoteId !== null, fn ($q) => $q->where('external_id', $remoteId))
            ->delete();

        $this->deviceAliasQuery($asset->organization_id, $provider)
            ->where('referenceable_type', $asset->getMorphClass())
            ->where('referenceable_id', $asset->getKey())
            ->when($remoteId !== null, fn ($q) => $q->where('external_id', $remoteId))
            ->delete();
    }

    /** Erste hinterlegte Geräte-ID des Anbieters (Kompatibilitäts-Helfer). */
    public function remoteId(Asset $asset, string $provider): ?string {
        return $this->remoteIds($asset, $provider)[0] ?? null;
    }

    /**
     * Alle Geräte-IDs des Anbieters für dieses Gerät: Primär-Referenz zuerst,
     * danach Alias-IDs (Zusatz-IDs aus Neuinstallation/Merge).
     *
     * @return array<int, string>
     */
    public function remoteIds(Asset $asset, string $provider): array {
        $primary = ExternalReference::query()
            ->forPlugin($asset->organization_id, RemoteSupportPlugin::ID, self::deviceType($provider))
            ->forReferenceable($asset)
            ->orderBy('id')
            ->pluck('external_id');

        $aliases = $this->deviceAliasQuery($asset->organization_id, $provider)
            ->where('referenceable_type', $asset->getMorphClass())
            ->where('referenceable_id', $asset->getKey())
            ->orderBy('id')
            ->pluck('external_id');

        return $primary
            ->merge($aliases)
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Überführt die Fernwartungsdaten eines (doppelt angelegten) Geräts auf
     * das Zielgerät: alle Geräte-IDs (Primär-Referenzen wandern um; kollidiert
     * die Primär-Referenz mit einer vorhandenen des Ziels, wird die Quell-ID
     * zum Alias) sowie sämtliche Pending-Sitzungen. Gebuchte Zeiteinträge
     * bleiben unberührt; das Quellgerät wird NICHT gelöscht/archiviert.
     *
     * @return array{ids: int, sessions: int}
     */
    public function mergeRemoteDevice(Asset $source, Asset $target): array {
        if ($source->getKey() === $target->getKey() || (int) $source->organization_id !== (int) $target->organization_id) {
            return ['ids' => 0, 'sessions' => 0];
        }

        $ids = 0;

        $primaries = ExternalReference::query()
            ->forPlugin($source->organization_id, RemoteSupportPlugin::ID)
            ->whereIn('external_type', array_values(self::DEVICE_TYPES))
            ->forReferenceable($source)
            ->get();

        foreach ($primaries as $ref) {
            $targetHasPrimary = ExternalReference::query()
                ->withoutGlobalScopes()
                ->where('plugin_id', RemoteSupportPlugin::ID)
                ->where('external_type', $ref->external_type)
                ->forReferenceable($target)
                ->exists();

            if ($targetHasPrimary) {
                ExternalReferenceAlias::query()->withoutGlobalScopes()->updateOrCreate(
                    [
                        'organization_id' => $source->organization_id,
                        'plugin_id' => RemoteSupportPlugin::ID,
                        'external_type' => $ref->external_type,
                        'external_id' => $ref->external_id,
                    ],
                    [
                        'referenceable_type' => $target->getMorphClass(),
                        'referenceable_id' => $target->getKey(),
                    ],
                );
                $ref->delete();
            } else {
                $ref->update(['referenceable_id' => $target->getKey(), 'synced_at' => now()]);
            }
            $ids++;
        }

        $ids += ExternalReferenceAlias::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $source->organization_id)
            ->where('plugin_id', RemoteSupportPlugin::ID)
            ->whereIn('external_type', array_values(self::DEVICE_TYPES))
            ->where('referenceable_type', $source->getMorphClass())
            ->where('referenceable_id', $source->getKey())
            ->update(['referenceable_id' => $target->getKey()]);

        $sessions = RemotePendingSession::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $source->organization_id)
            ->where('asset_id', $source->getKey())
            ->update(['asset_id' => $target->getKey()]);

        return ['ids' => (int) $ids, 'sessions' => (int) $sessions];
    }

    /** external_type des Geräte-Links für einen Provider (auch für den Importer). */
    public static function deviceType(string $provider): string {
        return self::DEVICE_TYPES[$provider] ?? throw new \InvalidArgumentException("Unknown remote provider: {$provider}");
    }
}
