<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FakeCardDavGateway.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Support;

use App\Plugins\CardDav\Contracts\CardDavGateway;
use App\Plugins\CardDav\Services\{CardDavAddressbook, CardDavSyncPage};
use RuntimeException;

/**
 * Test-Double für {@see CardDavGateway} (Bauturbo A9): liefert vorkonfigurierte
 * Discovery-/Sync-Ergebnisse und protokolliert die empfangenen Sync-Token und
 * lokalen ETag-Stände — kein HTTP. Damit sind sync-collection-Fortschreibung
 * (Token-Weitergabe) und die ETag-Fallback-Grundlage (localEtags) prüfbar.
 */
class FakeCardDavGateway implements CardDavGateway {
    /** @var list<string> je Aufruf empfangenes prevSyncToken */
    public array $receivedTokens = [];

    /** @var list<array<string, string>> je Aufruf empfangene lokale ETags (href → etag) */
    public array $receivedLocalEtags = [];

    /** @var list<string> je Aufruf synchronisierte Adressbuch-URL */
    public array $receivedAddressbookUrls = [];

    public int $syncCalls = 0;

    /**
     * @param  list<CardDavSyncPage>  $pages  Ergebnis je Aufruf (letzte Seite wiederholt sich)
     * @param  list<CardDavAddressbook>  $addressbooks
     */
    public function __construct(
        public array $pages = [],
        public array $addressbooks = [],
        public bool $pingOk = true,
        public bool $failSync = false,
    ) {}

    public function ping(): bool {
        return $this->pingOk;
    }

    public function discoverAddressbooks(): array {
        return $this->addressbooks;
    }

    public function syncAddressbook(string $addressbookUrl, string $prevSyncToken, array $localEtags): CardDavSyncPage {
        $this->syncCalls++;
        $this->receivedAddressbookUrls[] = $addressbookUrl;
        $this->receivedTokens[] = $prevSyncToken;
        $this->receivedLocalEtags[] = $localEtags;

        if ($this->failSync) {
            throw new RuntimeException('CardDAV server unreachable (fake).');
        }

        $index = min($this->syncCalls - 1, count($this->pages) - 1);
        $page = $this->pages[$index] ?? null;
        if ($page === null) {
            return new CardDavSyncPage([], [], 'sync-token-empty');
        }

        return $page;
    }
}
