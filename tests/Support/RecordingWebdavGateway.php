<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecordingWebdavGateway.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Support;

use App\Plugins\Webdav\Contracts\WebdavGateway;

/**
 * Test-Double für {@see WebdavGateway} (Feature 058, MVP-127): protokolliert
 * PUT-/MKCOL-Aufrufe und liefert konfigurierbare Ergebnisse + Server-Signatur
 * (für die Konflikterkennung) — kein HTTP.
 */
class RecordingWebdavGateway implements WebdavGateway {
    /** @var list<string> */
    public array $puts = [];

    /** @var list<string> */
    public array $collections = [];

    public function __construct(
        public bool $putOk = true,
        public bool $collectionOk = true,
        public ?string $signature = 'etag-1',
        public bool $pingOk = true,
        public ?string $downloadBody = 'REMOTE-CONTENT',
    ) {}

    public function ensureCollection(string $collectionPath): bool {
        $this->collections[] = $collectionPath;

        return $this->collectionOk;
    }

    public function putFile(string $path, string $contents, string $mime): bool {
        $this->puts[] = $path;

        return $this->putOk;
    }

    public function getFile(string $path): ?string {
        return $this->downloadBody;
    }

    public function remoteSignature(string $path): ?string {
        return $this->signature;
    }

    public function ping(): bool {
        return $this->pingOk;
    }
}
