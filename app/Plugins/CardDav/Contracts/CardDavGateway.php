<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CardDavGateway.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\CardDav\Contracts;

use App\Plugins\CardDav\Services\{CardDavAddressbook, CardDavSyncPage};

/**
 * Lesendes CardDAV-Gateway (Bauturbo A9, MVP-329). Kapselt die Client-Lib
 * (mstilkerich/carddavclient: RFC-6764-Discovery, RFC-6578-sync-collection mit
 * transparentem ETag-/PROPFIND-Fallback), damit Tests eine Fake-Implementierung
 * ohne HTTP binden können — dasselbe Muster wie {@see \App\Plugins\CalDav\Contracts\CalDavGateway}.
 */
interface CardDavGateway {
    /** Erreichbarkeit + Zugangsdaten prüfen (Healthcheck). */
    public function ping(): bool;

    /**
     * RFC-6764-Discovery: .well-known/carddav → current-user-principal →
     * addressbook-home-set → Adressbücher auflisten.
     *
     * @return list<CardDavAddressbook>
     */
    public function discoverAddressbooks(): array;

    /**
     * Delta-Sync eines Adressbuchs: sync-collection-Report (RFC 6578) mit
     * `$prevSyncToken`; Server ohne sync-collection fallen transparent auf den
     * ETag-Vergleich gegen `$localEtags` (href → etag) zurück.
     *
     * @param  array<string, string>  $localEtags
     */
    public function syncAddressbook(string $addressbookUrl, string $prevSyncToken, array $localEtags): CardDavSyncPage;
}
