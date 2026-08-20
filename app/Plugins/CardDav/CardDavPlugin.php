<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CardDavPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\CardDav;

use App\Models\CardDavConnection;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\CardDav\Contracts\CardDavGatewayFactory;
use App\Plugins\Contracts\Plugin;
use Throwable;

/**
 * CardDAV-Lesegateway als Matching-Quelle (Bauturbo A9, MVP-329).
 *
 * - **Liest** Kontakte aus einem self-hosted CardDAV-Adressbuch
 *   (Nextcloud/Radicale/Baïkal, RFC 6352) — Datenhoheit bleibt beim Kunden.
 * - **Inbox-First** (MVP-103): Kontakte werden über den IntegrationResolver
 *   als Zuordnungsvorschläge zu Kunden eingespeist — kein Auto-Merge, kein
 *   Direkt-Schreiben, keine Neuanlage.
 * - **Idempotent** über UID+ETag ({@see \App\Models\CardDavCard}-Spiegel);
 *   Delta-Sync per RFC-6578-sync-collection mit ETag-Fallback.
 *
 * Bewusst KEINE Capability: {@see \App\Plugins\Contracts\ContactSyncer} ist
 * der Push-Vertrag (workDiary → extern), dieses Plugin ist rein lesend.
 */
class CardDavPlugin extends AbstractPlugin {
    public const ID = 'carddav';

    public const SERVICE_PROVIDER = CardDavServiceProvider::class;

    public function name(): string {
        return 'CardDAV';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return (string) __('carddav.description');
    }

    public function capabilities(): array {
        return [];
    }

    public function adminPanel(): ?array {
        return [
            'route' => 'admin.carddav.index',
            'label' => __('CardDAV'),
            'icon' => 'group',
        ];
    }

    /** Per-Org-Konfiguration liegt in `carddav_connections` (Admin-Panel), nicht in plugin_settings. */
    public function settingsSchema(): array {
        return [];
    }

    /** Health-Check je Organisation: Anbindung suchen und den Server anpingen. */
    public function healthCheck(): PluginHealth {
        $org = $this->healthOrgContext();
        if ($org instanceof PluginHealth) {
            return $org;
        }

        $connection = CardDavConnection::query()->where('organization_id', $org->id)->first();
        if (! $connection instanceof CardDavConnection) {
            return PluginHealth::degraded(__('carddav.health.no_connection'));
        }
        if (! $connection->isActive()) {
            return PluginHealth::degraded(__('carddav.health.inactive_or_incomplete'));
        }

        try {
            return app(CardDavGatewayFactory::class)->for($connection)->ping()
                ? PluginHealth::ok(__('Verbunden mit :url.', ['url' => $connection->base_url]))
                : PluginHealth::failing(__('carddav.health.unreachable'), 'unreachable');
        } catch (Throwable $e) {
            return PluginHealth::failing(__('carddav.health.error', ['class' => class_basename($e)]));
        }
    }
}
