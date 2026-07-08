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

/**
 * Test-Double für {@see CalDavGateway} (Feature 058): protokolliert PUT-/
 * DELETE-Aufrufe und liefert konfigurierbare Ergebnisse — kein HTTP.
 */
class RecordingCalDavGateway implements CalDavGateway {
    /** @var list<string> */
    public array $puts = [];

    /** @var list<string> */
    public array $deletes = [];

    public function __construct(
        public bool $putOk = true,
        public bool $deleteOk = true,
        public bool $pingOk = true,
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
}
