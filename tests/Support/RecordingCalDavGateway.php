<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecordingCalDavGateway.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Support;

use App\Plugins\CalDav\Contracts\CalDavGateway;
use App\Plugins\CalDav\Services\CalDavSyncPage;
use DateTimeInterface;

/**
 * Test-Double für {@see CalDavGateway} (Feature 058): protokolliert PUT-/
 * DELETE-Aufrufe und liefert konfigurierbare Ergebnisse — kein HTTP.
 */
class RecordingCalDavGateway implements CalDavGateway {
    /** @var list<string> */
    public array $puts = [];

    /** @var list<string> */
    public array $deletes = [];

    /** @var list<string> */
    public array $seenSyncTokens = [];

    /** @var list<array<string, string>> */
    public array $seenEtags = [];

    public function __construct(
        public bool $putOk = true,
        public bool $deleteOk = true,
        public bool $pingOk = true,
        public ?CalDavSyncPage $syncPage = null,
    ) {}

    public function putObject(string $objectName, string $ics): bool {
        $this->puts[] = $objectName;

        return $this->putOk;
    }

    public function deleteObject(string $objectName): bool {
        $this->deletes[] = $objectName;

        return $this->deleteOk;
    }

    public function ping(): bool {
        return $this->pingOk;
    }

    /**
     * Rückimport (MVP-610b): liefert die vorbereitete Seite und merkt sich das
     * übergebene Token — Tests prüfen daran den Wiederanlaufpunkt.
     *
     * @param  array<string, string>  $localEtags
     */
    public function syncEvents(string $prevSyncToken, array $localEtags, DateTimeInterface $windowStart, DateTimeInterface $windowEnd): CalDavSyncPage {
        $this->seenSyncTokens[] = $prevSyncToken;
        $this->seenEtags[] = $localEtags;

        return $this->syncPage ?? new CalDavSyncPage([], [], '');
    }
}
