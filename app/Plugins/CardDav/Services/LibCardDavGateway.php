<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LibCardDavGateway.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\CardDav\Services;

use App\Models\CardDavConnection;
use App\Plugins\CardDav\Contracts\CardDavGateway;
use MStilkerich\CardDavClient\{Account, AddressbookCollection, Config};
use MStilkerich\CardDavClient\Services\{Discovery, Sync};
use Throwable;

/**
 * Produktiv-Gateway (Bauturbo A9, MVP-329) auf Basis von
 * mstilkerich/carddavclient (Roundcube-erprobt): RFC-6764-Discovery
 * (SRV + .well-known/carddav + current-user-principal → addressbook-home-set),
 * RFC-6578-sync-collection mit transparentem ETag-/PROPFIND-Fallback und
 * addressbook-multiget; vCards kommen bereits als sabre/vobject-Objekte an.
 *
 * SSRF: die org-konfigurierte Basis-URL wird VOR jedem HTTP-Kontakt gegen
 * {@see CardDavUrlGuard} geprüft (auditiertes Private-Network-Opt-in wie
 * JTL-Wawi, Whitebox-Konvention 2026-07). Basic-Auth mit dem verschlüsselten
 * App-Passwort; bei 2FA-Servern (Nextcloud) sind App-Passwörter Pflicht.
 */
class LibCardDavGateway implements CardDavGateway {
    public function __construct(private readonly CardDavConnection $connection) {
        CardDavUrlGuard::assertAcceptable((string) $connection->base_url, $connection->allowsPrivateNetwork());

        // Lib-Logger einmalig initialisieren (NullLogger) — Fehler laufen über
        // die Exceptions der Lib in die Verbindungs-Gesundheit (HasConnectionHealth).
        Config::init();
    }

    public function ping(): bool {
        try {
            $addressbookUrl = (string) $this->connection->addressbook_url;
            if ($addressbookUrl !== '') {
                // Displayname-PROPFIND auf das gewählte Adressbuch: prüft
                // Erreichbarkeit UND Zugangsdaten in einem Rutsch.
                (new AddressbookCollection($addressbookUrl, $this->account()))->getName();

                return true;
            }

            return $this->discoverAddressbooks() !== [];
        } catch (Throwable) {
            return false;
        }
    }

    public function discoverAddressbooks(): array {
        $books = (new Discovery())->discoverAddressbooks($this->account());

        return array_map(static function (AddressbookCollection $book): CardDavAddressbook {
            try {
                $name = $book->getName();
            } catch (Throwable) {
                $name = $book->getUriPath();
            }

            return new CardDavAddressbook($book->getUri(), $name);
        }, $books);
    }

    public function syncAddressbook(string $addressbookUrl, string $prevSyncToken, array $localEtags): CardDavSyncPage {
        $handler = new CollectingSyncHandler($localEtags);
        $abook = new AddressbookCollection($addressbookUrl, $this->account());

        $newToken = (new Sync())->synchronize($abook, $handler, [], $prevSyncToken);

        return new CardDavSyncPage($handler->changed, $handler->deleted, $newToken);
    }

    private function account(): Account {
        return new Account(
            (string) $this->connection->base_url,
            [
                'username' => (string) $this->connection->username,
                'password' => (string) $this->connection->app_password,
                // Erspart den 401-Roundtrip pro Request (Basic ist ohnehin gesetzt).
                'preemptive_basic_auth' => true,
            ],
            baseUrl: (string) $this->connection->base_url,
        );
    }
}
