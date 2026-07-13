<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MirrorTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Mirror;

/**
 * Vertrag eines Ablage-Ziels (MVP-330, Bauturbo A10): kapselt je Plugin
 * (WebDAV, SharePoint, …) die Verbindungs-Auflösung, den Gateway-Bau und die
 * plugin-eigenen Kennungen für den GEMEINSAMEN Spiegel-Kern — Observer,
 * Outbox-Dispatcher, Spiegel-Service und Konfliktauflösung arbeiten
 * ausschließlich gegen diesen Vertrag (Analogie: `Support/Calendar` aus A8).
 */
interface MirrorTarget {
    /** Plugin-ID (`webdav`, `sharepoint`) — zugleich Outbox-/Referenz-/Inbox-Kennung. */
    public function pluginId(): string;

    /** Betriebsbereite Verbindung der Organisation, sonst null. */
    public function activeConnection(int $organizationId): ?MirrorConnection;

    /** Transport-Gateway zur Verbindung (WebDAV-HTTP bzw. Microsoft Graph). */
    public function gatewayFor(MirrorConnection $connection): RemoteFileGateway;

    /**
     * documents-Spalte des „Spiegelung getrennt"-Markers dieses Zweigs
     * (z. B. `webdav_mirror_detached`) — Trennung wirkt nur je Ablage-Ziel.
     */
    public function detachedAttribute(): string;

    /**
     * Outbox-Idempotenzschlüssel für ein Spiegel-Suffix. Der Unique-Index der
     * Outbox ist (organization_id, idempotency_key) OHNE plugin_id — jedes
     * Ziel MUSS daher einen eigenen Schlüsselraum verwenden (WebDAV behält
     * das historische `mirror:`-Präfix, neue Ziele präfixen ihre Plugin-ID).
     */
    public function idempotencyKey(string $suffix): string;
}
