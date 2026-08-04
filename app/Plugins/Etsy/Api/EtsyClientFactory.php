<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EtsyClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Etsy\Api;

use App\Models\EtsyConnection;
use App\Plugins\Etsy\EtsyConfig;
use App\Plugins\Support\PluginHttpFactory;
use RuntimeException;

/**
 * Baut den typisierten Etsy-Client je Organisation aus der aufgelösten
 * Konfiguration (plugin_settings → ENV-Fallback) und der org-gebundenen
 * OAuth-Verbindung. Die {@see PluginHttpFactory} wird ERST zur Aufrufzeit
 * aus dem Container gelöst — Tests binden den Fake-Transport
 * (FakePluginHttp).
 */
class EtsyClientFactory {
    /** Client zur bestehenden Verbindung (Sync/Outbox/Health). */
    public function for(EtsyConnection $connection): EtsyClient {
        return $this->build($connection, (int) $connection->organization_id);
    }

    /** Client direkt nach dem OAuth-Callback (Shop-Ermittlung). */
    public function forOrganization(int $organizationId): EtsyClient {
        $connection = EtsyConnection::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->first();
        if (! $connection instanceof EtsyConnection) {
            throw new RuntimeException((string) __('Keine Etsy-Verbindung für diese Organisation.'));
        }

        return $this->build($connection, $organizationId);
    }

    private function build(EtsyConnection $connection, int $organizationId): EtsyClient {
        $config = EtsyConfig::resolve($organizationId);
        if (($config['keystring'] ?? '') === '' || ($config['shared_secret'] ?? '') === '') {
            throw new RuntimeException((string) __('Etsy ist für diese Organisation nicht konfiguriert (Keystring/Shared Secret der Seller-App fehlen).'));
        }

        return new EtsyClient(
            app(PluginHttpFactory::class),
            $connection,
            (string) $config['keystring'],
            (string) $config['shared_secret'],
            (string) $config['base_url'],
            (float) config('plugins.etsy.request_interval', 0.2),
        );
    }
}
