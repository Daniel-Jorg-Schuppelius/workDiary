<?php
/*
 * Created on   : Mon Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnvWriter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Install;

use RuntimeException;

/**
 * Schreibt gezielt einzelne Schlüssel in die .env-Datei, ohne die übrigen
 * Zeilen (Kommentare, Reihenfolge, nicht betroffene Keys) zu verändern.
 *
 * Bewusst minimalistisch: Der Installer ruft setMany() pro Schritt auf und
 * persistiert die Werte direkt auf der Platte, da vor dem Setzen des APP_KEY
 * keine Session zur Verfügung steht (siehe InstallationManager).
 */
final class EnvWriter {
    public function __construct(private readonly string $path) {
    }

    public static function forApp(): self {
        return new self(base_path('.env'));
    }

    public function exists(): bool {
        return is_file($this->path);
    }

    public function path(): string {
        return $this->path;
    }

    /**
     * Liest den aktuellen Wert eines Keys aus der .env (ohne Anführungszeichen).
     */
    public function get(string $key): ?string {
        $contents = $this->read();
        if (preg_match('/^' . preg_quote($key, '/') . '=(.*)$/m', $contents, $m) === 1) {
            return $this->unquote(trim($m[1]));
        }

        return null;
    }

    /**
     * Setzt mehrere Schlüssel/Werte. Bestehende Keys werden ersetzt, fehlende
     * am Ende ergänzt. Werte mit Sonderzeichen werden automatisch gequotet.
     *
     * @param  array<string, string|int|bool|null>  $values
     */
    public function setMany(array $values): void {
        $contents = $this->read();

        foreach ($values as $key => $value) {
            $line = $key . '=' . $this->quote($this->stringify($value));
            $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

            if (preg_match($pattern, $contents) === 1) {
                $contents = (string) preg_replace($pattern, $this->escapeReplacement($line), $contents);
            } else {
                $contents = rtrim($contents, "\n") . "\n" . $line . "\n";
            }
        }

        $this->write($contents);
    }

    public function set(string $key, string|int|bool|null $value): void {
        $this->setMany([$key => $value]);
    }

    /**
     * Stellt sicher, dass die .env existiert (kopiert .env.example, falls nötig).
     */
    public function ensureFileExists(): void {
        if ($this->exists()) {
            return;
        }

        $example = base_path('.env.example');
        if (is_file($example)) {
            if (! @copy($example, $this->path)) {
                throw new RuntimeException('Konnte .env nicht aus .env.example erzeugen: ' . $this->path);
            }

            return;
        }

        $this->write("APP_NAME=WorkDiary\nAPP_ENV=production\nAPP_KEY=\nAPP_DEBUG=false\n");
    }

    private function read(): string {
        if (! $this->exists()) {
            return '';
        }

        $contents = @file_get_contents($this->path);
        if ($contents === false) {
            throw new RuntimeException('Konnte .env nicht lesen: ' . $this->path);
        }

        return $contents;
    }

    private function write(string $contents): void {
        if (@file_put_contents($this->path, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Konnte .env nicht schreiben: ' . $this->path);
        }
    }

    private function stringify(string|int|bool|null $value): string {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    private function quote(string $value): string {
        if ($value === '') {
            return '';
        }

        // Werte mit Leerzeichen, Rauten oder Anführungszeichen müssen gequotet werden.
        if (preg_match('/[\s#"\'=]/', $value) === 1) {
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
        }

        return $value;
    }

    private function unquote(string $value): string {
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'")) {
            $quote = $value[0];
            if (str_ends_with($value, $quote)) {
                $inner = substr($value, 1, -1);

                return $quote === '"' ? str_replace(['\\"', '\\\\'], ['"', '\\'], $inner) : $inner;
            }
        }

        return $value;
    }

    private function escapeReplacement(string $line): string {
        // preg_replace interpretiert $ und \ im Ersatzstring — neutralisieren.
        return str_replace(['\\', '$'], ['\\\\', '\\$'], $line);
    }
}
