<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SharepointMirrorTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Sharepoint;

use App\Models\SharepointConnection;
use App\Plugins\Sharepoint\Api\SharepointDriveClient;
use App\Plugins\Support\Mirror\{MirrorConnection, MirrorTarget, RemoteFileGateway};

/**
 * SharePoint als Ablage-Ziel des gemeinsamen Spiegel-Kerns (MVP-330,
 * Bauturbo A10): Verbindungs-Auflösung je Organisation, Graph-Gateway und die
 * plugin-eigenen Kennungen. Der Idempotenzschlüssel trägt die Plugin-ID als
 * Präfix — der Outbox-Unique-Index (organization_id, idempotency_key) kennt
 * kein plugin_id, WebDAV- und SharePoint-Spiegelungen desselben Dokuments
 * dürfen nicht kollidieren.
 */
class SharepointMirrorTarget implements MirrorTarget {
    public function pluginId(): string {
        return SharepointPlugin::ID;
    }

    public function activeConnection(int $organizationId): ?MirrorConnection {
        $connection = SharepointConnection::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->first();

        return $connection instanceof SharepointConnection && $connection->isActive() ? $connection : null;
    }

    public function gatewayFor(MirrorConnection $connection): RemoteFileGateway {
        assert($connection instanceof SharepointConnection);

        return new SharepointDriveClient($connection);
    }

    public function detachedAttribute(): string {
        return 'sharepoint_mirror_detached';
    }

    public function idempotencyKey(string $suffix): string {
        return SharepointPlugin::ID . ':mirror:' . $suffix;
    }
}
