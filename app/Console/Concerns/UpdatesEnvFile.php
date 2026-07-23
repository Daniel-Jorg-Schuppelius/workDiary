<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UpdatesEnvFile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Concerns;

use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
use Throwable;

/**
 * Schreibt einzelne KEY=VALUE-Einträge in die .env der Installation —
 * gemeinsame Basis der Backup-Token-/Schlüssel-Kommandos (MVP-046/362).
 */
trait UpdatesEnvFile {
    /** Pfad zur aktiven, beschreibbaren .env oder null. */
    protected function writableEnvPath(): ?string {
        $path = app()->environmentFilePath();

        return $path !== '' && is_file($path) && is_writable($path) ? $path : null;
    }

    /** Prüft, ob KEY in der .env mit nicht-leerem Wert gesetzt ist. */
    protected function envHasValue(string $envPath, string $key): bool {
        try {
            $contents = ToolkitFile::read($envPath);
        } catch (Throwable) {
            return false;
        }

        return preg_match('/^' . preg_quote($key, '/') . '=..*$/m', $contents) === 1;
    }

    /** Ersetzt `KEY=...` in der .env bzw. hängt die Zeile an. */
    protected function writeEnvValue(string $envPath, string $key, string $value): bool {
        try {
            $contents = ToolkitFile::read($envPath);
            $replacement = $key . '=' . $value;
            $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

            if (preg_match($pattern, $contents) === 1) {
                $contents = (string) preg_replace($pattern, $replacement, $contents);
            } else {
                if ($contents !== '' && ! str_ends_with($contents, "\n")) {
                    $contents .= "\n";
                }
                $contents .= $replacement . "\n";
            }

            ToolkitFile::write($envPath, $contents);
        } catch (Throwable) {
            return false;
        }

        return true;
    }
}
