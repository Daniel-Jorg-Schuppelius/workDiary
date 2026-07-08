<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebdavGateway.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Webdav\Contracts;

/**
 * Schmaler, testbarer Zugriff auf eine WebDAV-Ablage (Feature 058, MVP-127):
 * Ordner sicherstellen (MKCOL), Datei hochladen (PUT) und die aktuelle
 * Server-Signatur einer Datei ermitteln (HEAD → ETag) für die
 * Konflikterkennung. Kapselt HTTP/Auth; im Test gemockt (kein echter Server).
 */
interface WebdavGateway {
    /** Stellt die (ggf. mehrstufige) Collection sicher. true = vorhanden/angelegt. */
    public function ensureCollection(string $collectionPath): bool;

    /** Lädt die Datei hoch (Überschreiben). true = erfolgreich (2xx). */
    public function putFile(string $path, string $contents, string $mime): bool;

    /**
     * Lädt den Inhalt der Datei herunter (GET). `null`, wenn nicht vorhanden
     * oder Fehler. Für die Konfliktauflösung „Remote als neue lokale Version
     * importieren" (Rang 18).
     */
    public function getFile(string $path): ?string;

    /**
     * Aktuelle Server-Signatur der Datei (ETag, sonst Last-Modified|Length).
     * `null`, wenn die Datei nicht existiert oder der Server nicht antwortet —
     * dient der Erkennung externer Änderungen VOR dem Überschreiben.
     */
    public function remoteSignature(string $path): ?string;

    /** Liveness/Auth-Check: true, wenn die Ablage mit den Zugangsdaten erreichbar ist. */
    public function ping(): bool;
}
