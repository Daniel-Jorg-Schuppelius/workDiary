<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CollectingSyncHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\CardDav\Services;

use MStilkerich\CardDavClient\Services\SyncHandler;
use Sabre\VObject\Component\VCard;

/**
 * SyncHandler-Adapter (Bauturbo A9): sammelt die von der Client-Lib gemeldeten
 * Änderungen/Löschungen ein, statt sie sofort zu verarbeiten — der
 * {@see CardDavContactImporter} entscheidet anschließend transaktional über
 * Inbox-Einspeisung und Spiegel-Fortschreibung. `$localEtags` (href → etag)
 * speist den ETag-Fallback der Lib für Server ohne sync-collection.
 */
class CollectingSyncHandler implements SyncHandler {
    /** @var list<CardDavCardChange> */
    public array $changed = [];

    /** @var list<string> */
    public array $deleted = [];

    /**
     * @param  array<string, string>  $localEtags
     */
    public function __construct(private readonly array $localEtags) {}

    public function addressObjectChanged(string $uri, string $etag, ?VCard $card): void {
        $this->changed[] = new CardDavCardChange($uri, $etag, $card);
    }

    public function addressObjectDeleted(string $uri): void {
        $this->deleted[] = $uri;
    }

    /** @return array<string, string> */
    public function getExistingVCardETags(): array {
        return $this->localEtags;
    }

    public function finalizeSync(): void {
        // Verarbeitung erfolgt bewusst nachgelagert im Importer.
    }
}
