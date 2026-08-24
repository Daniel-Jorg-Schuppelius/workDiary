<?php
/*
 * Created on   : Sat Jul 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChunkedFileReader.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Backup;

use RuntimeException;

/**
 * Chunkweises Lesen für Backup-Uploads: dünne Fach-Naht über
 * `File::readChunks(strict: true)` (common-toolkit v1.26, Vollscan C9) —
 * die Klasse bündelt, was app-spezifisch bleibt: (chunk, startOffset) für
 * Resumable-Uploads, Gesamt-Bytezahl und die RuntimeException-Semantik der
 * Backup-Clients.
 */
final class ChunkedFileReader {
    /**
     * Lesbarkeits-/Größen-Check VOR der Session-Eröffnung — gleicher
     * Fehlertext wie beim eigentlichen Öffnen in {@see each()}.
     */
    public static function size(string $localPath): int {
        $size = @filesize($localPath);
        if ($size === false || ! is_readable($localPath)) {
            throw new RuntimeException("Backup-Teil nicht lesbar: {$localPath}");
        }

        return (int) $size;
    }

    /**
     * Liest die Datei chunkweise und ruft den Callback je NICHT-leerem Chunk
     * mit (chunk, startOffset) auf; das Handle wird immer geschlossen.
     * Rückgabe: Gesamtzahl gelesener Bytes.
     *
     * @param  callable(string $chunk, int $offset): void  $onChunk
     */
    public static function each(string $localPath, int $chunkSize, callable $onChunk): int {
        if (@filesize($localPath) === false) {
            throw new RuntimeException("Backup-Teil nicht lesbar: {$localPath}");
        }

        // Seit common-toolkit v1.26 (Vollscan 2026-08-23, C9): readChunks im
        // strict-Modus wirft bei Lesefehlern statt still abzubrechen — die
        // frühere App-eigene Leseschleife entfällt; (chunk, offset) und die
        // Gesamt-Bytezahl bleiben App-Vertrag der Backup-Uploads.
        try {
            $offset = 0;
            foreach (\CommonToolkit\Helper\FileSystem\File::readChunks($localPath, max(1, $chunkSize), null, strict: true) as $chunk) {
                $onChunk($chunk, $offset);
                $offset += strlen($chunk);
            }

            return $offset;
        } catch (\ERRORToolkit\Exceptions\FileSystemException $e) {
            throw new RuntimeException("Lesefehler in {$localPath}: {$e->getMessage()}", 0, $e);
        }
    }
}
