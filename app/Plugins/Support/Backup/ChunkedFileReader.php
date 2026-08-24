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
 * Chunkweises Lesen für Backup-Uploads.
 *
 * BEWUSST app-lokal (Vollscan 2026-08-23, C9 — Klasse F): `File::readChunks`
 * im common-toolkit bricht bei `fread === false` STILL ab (break) — für
 * Backup-Teile wäre das ein stiller Datenverlust; hier ist ein Lesefehler
 * eine RuntimeException. Zusätzlich braucht der Upload (chunk, startOffset)
 * und die Gesamt-Bytezahl. Toolkit-Erweiterung (strict-Modus) ist als
 * Welle-6-Kandidat notiert; bis dahin bleibt diese Klasse die Wahrheit.
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
        $size = @filesize($localPath);
        $in = @fopen($localPath, 'rb');
        if ($in === false || $size === false) {
            throw new RuntimeException("Backup-Teil nicht lesbar: {$localPath}");
        }

        try {
            $offset = 0;
            while (! feof($in)) {
                $chunk = fread($in, max(1, $chunkSize));
                if ($chunk === false) {
                    throw new RuntimeException("Lesefehler in {$localPath}.");
                }
                if ($chunk === '') {
                    continue;
                }
                $onChunk($chunk, $offset);
                $offset += strlen($chunk);
            }

            return $offset;
        } finally {
            fclose($in);
        }
    }
}
