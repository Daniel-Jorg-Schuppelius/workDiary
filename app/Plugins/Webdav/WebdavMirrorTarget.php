<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebdavMirrorTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Webdav;

use App\Models\WebdavConnection;
use App\Plugins\Support\Mirror\{MirrorConnection, MirrorTarget, RemoteFileGateway};
use App\Plugins\Webdav\Contracts\WebdavGatewayFactory;

/**
 * WebDAV als Ablage-Ziel des gemeinsamen Spiegel-Kerns (MVP-330, Bauturbo A10;
 * Fachlogik unverändert Feature 058/MVP-127): Verbindungs-Auflösung je
 * Organisation, Gateway über die austauschbare {@see WebdavGatewayFactory}
 * (Tests binden eine Fake-Factory ohne HTTP) und die historischen Kennungen —
 * `mirror:`-Idempotenzpräfix und `webdav_mirror_detached`-Marker bleiben
 * rückwärtskompatibel zu Bestands-Outbox/-Referenzen.
 */
class WebdavMirrorTarget implements MirrorTarget {
    public function pluginId(): string {
        return WebdavPlugin::ID;
    }

    public function activeConnection(int $organizationId): ?MirrorConnection {
        $connection = WebdavConnection::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('active', true)
            ->first();

        return $connection instanceof WebdavConnection && $connection->isActive() ? $connection : null;
    }

    public function gatewayFor(MirrorConnection $connection): RemoteFileGateway {
        assert($connection instanceof WebdavConnection);

        return app(WebdavGatewayFactory::class)->for($connection);
    }

    public function detachedAttribute(): string {
        return 'webdav_mirror_detached';
    }

    /** Historisches Präfix (vor A10 plugin-los) — Bestandseinträge bleiben dedupe-wirksam. */
    public function idempotencyKey(string $suffix): string {
        return 'mirror:' . $suffix;
    }
}
