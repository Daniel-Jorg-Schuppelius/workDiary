<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebdavPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Webdav;

use App\Models\{Organization, PluginSetting, WebdavConnection};
use App\Plugins\Contracts\Plugin;
use App\Plugins\{PluginDefaults, PluginHealth};
use App\Plugins\Webdav\Contracts\WebdavGatewayFactory;
use Throwable;

/**
 * On-Premise-Dokumentablage über WebDAV (Feature 058, MVP-127).
 *
 * - **Spiegelt** freigegebene DMS-Dokumente ({@see \App\Models\Document},
 *   Status `Active`) in eine externe WebDAV-Ablage (Nextcloud/ownCloud,
 *   generisch) — nach Regel Dokumenttyp→Zielordner, mit Übergabenachweis
 *   (SHA-256 + Zeit + Ziel) in {@see \App\Models\ExternalReference}.
 * - WorkDiary bleibt führend und revisionssicher; **kein Rückkanal**. Externe
 *   Änderungen an gespiegelten Dateien führen zu sichtbaren Konflikten
 *   ({@see \App\Models\IntegrationInboxItem}), nie zu stiller Übernahme.
 * - Zustellung/Retry/Idempotenz über die generische Integrations-Outbox
 *   (Feature 055) via {@see Services\WebdavOutboxDispatcher}.
 *
 * Bewusst ohne Sync-Capability: die Spiegelung ist ereignisgetrieben
 * (Freigabe → Outbox), kein providerneutraler Abgleicheinstieg.
 */
class WebdavPlugin implements Plugin {
    use PluginDefaults;

    public const ID = 'webdav';

    public const SERVICE_PROVIDER = WebdavServiceProvider::class;

    public function id(): string {
        return self::ID;
    }

    public function name(): string {
        return 'WebDAV';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return __('Spiegelt freigegebene Dokumente in eine externe WebDAV-Ablage (Nextcloud/ownCloud) — mit Übergabenachweis und Konfliktanzeige, ohne Rückkanal.');
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

        return (bool) config('plugins.webdav.enabled', false);
    }

    /** Ereignisgetriebenes Sink-Plugin ohne providerneutrale Sync-Capability. */
    public function capabilities(): array {
        return [];
    }

    public function adminPanel(): ?array {
        return [
            'route' => 'admin.webdav.index',
            'label' => __('WebDAV'),
            'icon' => 'folder_shared',
        ];
    }

    public function serviceProvider(): ?string {
        return WebdavServiceProvider::class;
    }

    /** Per-Org-Konfiguration liegt in `webdav_connections` (Admin-Panel), nicht in plugin_settings. */
    public function settingsSchema(): array {
        return [];
    }

    public function isPerOrganization(): bool {
        return true;
    }

    /** Health-Check je Organisation: aktive Ablage suchen und die Collection anpingen. */
    public function healthCheck(): PluginHealth {
        $org = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        if (! $org instanceof Organization) {
            return PluginHealth::ok(__('Keine Organisation im Kontext.'));
        }

        $connection = WebdavConnection::query()->where('organization_id', $org->id)->first();
        if (! $connection instanceof WebdavConnection) {
            return PluginHealth::degraded(__('Keine WebDAV-Ablage hinterlegt.'));
        }
        if (! $connection->isActive()) {
            return PluginHealth::degraded(__('WebDAV-Ablage ist deaktiviert oder unvollständig.'));
        }

        try {
            return app(WebdavGatewayFactory::class)->for($connection)->ping()
                ? PluginHealth::ok(__('Verbunden mit :url.', ['url' => $connection->base_url]))
                : PluginHealth::failing(__('WebDAV-Ablage nicht erreichbar oder Zugangsdaten ungültig.'), 'unreachable');
        } catch (Throwable $e) {
            return PluginHealth::failing(__('WebDAV-Fehler (:class).', ['class' => class_basename($e)]));
        }
    }
}
