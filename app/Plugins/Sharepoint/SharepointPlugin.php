<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SharepointPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Sharepoint;

use App\Models\{Organization, PluginSetting, SharepointConnection};
use App\Plugins\Contracts\Plugin;
use App\Plugins\{PluginDefaults, PluginHealth};
use App\Plugins\Sharepoint\Api\SharepointDriveClient;
use Throwable;

/**
 * SharePoint-Dokumentablage über Microsoft Graph (MVP-330, Bauturbo A10) —
 * weiterer Mirror-Zweig neben der WebDAV-Ablage (Feature 058/MVP-127).
 *
 * - **Spiegelt** freigegebene DMS-Dokumente ({@see \App\Models\Document},
 *   Status `Active`) in eine SharePoint-Dokumentbibliothek — nach Regel
 *   Dokumenttyp→Zielordner, mit Übergabenachweis (SHA-256 + Zeit + Ziel) in
 *   {@see \App\Models\ExternalReference}. WebDAV gegen SharePoint Online ist
 *   tot (Legacy-Auth abgeschaltet) → Transport über Graph
 *   (`PUT …:/content` bzw. `createUploadSession` ab 4 MB).
 * - WorkDiary bleibt führend und revisionssicher; **kein Rückkanal**. Externe
 *   Änderungen an gespiegelten Dateien führen zu sichtbaren Konflikten
 *   ({@see \App\Models\IntegrationInboxItem}), nie zu stiller Übernahme.
 * - Zustellung/Retry/Idempotenz über die generische Integrations-Outbox
 *   (Feature 055) via gemeinsamem Spiegel-Kern
 *   {@see \App\Plugins\Support\Mirror\MirrorOutboxDispatcher}.
 *
 * Bewusst ohne Sync-Capability: die Spiegelung ist ereignisgetrieben
 * (Freigabe → Outbox), kein providerneutraler Abgleicheinstieg.
 */
class SharepointPlugin implements Plugin {
    use PluginDefaults;

    public const ID = 'sharepoint';

    public const SERVICE_PROVIDER = SharepointServiceProvider::class;

    public function id(): string {
        return self::ID;
    }

    public function name(): string {
        return 'SharePoint';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return __('sharepoint.plugin_description');
    }

    public function isEnabled(): bool {
        if (app()->bound('currentOrganization')) {
            $org = app('currentOrganization');
            if ($org instanceof Organization) {
                $row = PluginSetting::forOrganization($org->id, self::ID);
                if ($row->exists) {
                    return $row->enabled;
                }
            }
        }

        return (bool) config('plugins.sharepoint.enabled', false);
    }

    /** Ereignisgetriebenes Sink-Plugin ohne providerneutrale Sync-Capability. */
    public function capabilities(): array {
        return [];
    }

    public function adminPanel(): ?array {
        return [
            'route' => 'admin.sharepoint.index',
            'label' => __('sharepoint.title'),
            'icon' => 'cloud_upload',
        ];
    }

    public function serviceProvider(): ?string {
        return SharepointServiceProvider::class;
    }

    /** Keine per-Org-Secrets in plugin_settings: die Verbindung liegt in `sharepoint_connections`. */
    public function settingsSchema(): array {
        return [];
    }

    public function isPerOrganization(): bool {
        return true;
    }

    /** Health-Check je Organisation: billige Probe auf die gewählte Bibliothek. */
    public function healthCheck(): PluginHealth {
        if (! SharepointConfig::isConfigured()) {
            return PluginHealth::degraded(__('sharepoint.health.not_configured'));
        }

        $org = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        if (! $org instanceof Organization) {
            return PluginHealth::ok(__('sharepoint.health.no_org_context'));
        }

        $connection = SharepointConnection::query()->where('organization_id', $org->id)->first();
        if (! $connection instanceof SharepointConnection || $connection->status === SharepointConnection::STATUS_DISCONNECTED) {
            return PluginHealth::degraded(__('sharepoint.health.no_connection'));
        }
        if (! $connection->isActive()) {
            return PluginHealth::degraded(__('sharepoint.health.inactive'));
        }

        try {
            return (new SharepointDriveClient($connection))->ping()
                ? PluginHealth::ok(__('sharepoint.health.ok'))
                : PluginHealth::failing(__('sharepoint.health.failing'), 'unreachable');
        } catch (Throwable $e) {
            return PluginHealth::failing(__('sharepoint.health.error', ['class' => class_basename($e)]));
        }
    }
}
