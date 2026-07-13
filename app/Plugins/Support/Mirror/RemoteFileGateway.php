<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteFileGateway.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Mirror;

/**
 * Schmaler, testbarer Zugriff auf eine externe Dateiablage (MVP-330,
 * Bauturbo A10 — gehoben aus dem WebDAV-Plugin, Feature 058/MVP-127):
 * Ordner sicherstellen, Datei hochladen und die aktuelle Server-Signatur
 * einer Datei ermitteln (ETag/cTag) für die Konflikterkennung. Kapselt das
 * Transportprotokoll (WebDAV, Microsoft Graph, …); im Test gemockt.
 */
interface RemoteFileGateway {
    /** Stellt den (ggf. mehrstufigen) Zielordner sicher. true = vorhanden/angelegt. */
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
     * Aktuelle Server-Signatur der Datei (ETag/cTag, sonst Fallback).
     * `null`, wenn die Datei nicht existiert oder der Server nicht antwortet —
     * dient der Erkennung externer Änderungen VOR dem Überschreiben.
     */
    public function remoteSignature(string $path): ?string;

    /** Liveness/Auth-Check: true, wenn die Ablage mit den Zugangsdaten erreichbar ist. */
    public function ping(): bool;
}
