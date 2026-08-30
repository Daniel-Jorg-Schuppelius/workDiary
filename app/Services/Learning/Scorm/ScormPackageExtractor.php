<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScormPackageExtractor.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning\Scorm;

use ZipArchive;

/**
 * Entpackt ein SCORM-Paket sicher (Feature 149, MVP-743).
 *
 * Wie der Manifest-Parser **ohne Laravel-Abhängigkeit** — die Prüfungen
 * hier sind fachneutral und gehören später ins Toolkit.
 *
 * Die drei Gefahren beim Entpacken fremder Archive:
 *  1. **Zip-Slip**: Einträge wie `../../config/app.php` schreiben außerhalb
 *     des Zielordners. Jeder Pfad wird deshalb normalisiert und muss unter
 *     dem Ziel bleiben.
 *  2. **Ausführbarer Code**: ein SCORM-Paket ist HTML/JS/Medien. `.php`,
 *     `.phtml` & Co. werden nicht ausgepackt — der Auslieferungspfad darf
 *     kein Einfallstor werden.
 *  3. **Zip-Bomben**: entpackte Gesamtgröße und Dateizahl sind gedeckelt.
 */
class ScormPackageExtractor {
    /** Nicht auszupackende Endungen — alles, was ein Server ausführen könnte. */
    private const FORBIDDEN_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar',
        'phps', 'cgi', 'pl', 'py', 'sh', 'bash', 'exe', 'dll', 'so',
        'htaccess', 'htpasswd',
    ];

    public function __construct(
        private readonly int $maxBytes = 512 * 1024 * 1024,
        private readonly int $maxFiles = 5000,
    ) {}

    /**
     * @return array{manifest: string, files: int, bytes: int}
     */
    public function extract(string $zipPath, string $targetDir): array {
        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new ScormPackageException(ScormPackageException::UNREADABLE, 'Das Paket ließ sich nicht öffnen.');
        }

        try {
            if ($zip->numFiles > $this->maxFiles) {
                throw new ScormPackageException(ScormPackageException::TOO_MANY_FILES, 'Das Paket enthält zu viele Dateien.');
            }

            if (! is_dir($targetDir) && ! mkdir($targetDir, 0775, true) && ! is_dir($targetDir)) {
                throw new ScormPackageException(ScormPackageException::TARGET_UNWRITABLE, 'Der Zielordner ließ sich nicht anlegen.');
            }

            $root = $this->realTarget($targetDir);
            $manifest = null;
            $files = 0;
            $bytes = 0;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);

                if ($stat === false) {
                    continue;
                }

                $name = (string) $stat['name'];

                // Verzeichniseinträge selbst tragen keinen Inhalt.
                if (str_ends_with($name, '/')) {
                    continue;
                }

                if ($this->isForbidden($name)) {
                    continue;
                }

                $bytes += (int) $stat['size'];

                if ($bytes > $this->maxBytes) {
                    throw new ScormPackageException(ScormPackageException::TOO_LARGE, 'Das entpackte Paket ist zu groß.');
                }

                $destination = $this->safePath($root, $name);
                $directory = dirname($destination);

                if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                    throw new ScormPackageException(ScormPackageException::TARGET_UNWRITABLE, 'Ein Unterordner ließ sich nicht anlegen.');
                }

                $contents = $zip->getFromIndex($i);

                if ($contents === false) {
                    continue;
                }

                file_put_contents($destination, $contents);
                $files++;

                if (strcasecmp(basename($name), 'imsmanifest.xml') === 0 && $manifest === null) {
                    $manifest = $contents;
                }
            }

            if ($manifest === null) {
                throw new ScormPackageException(ScormPackageException::MANIFEST_MISSING, 'Im Paket fehlt die imsmanifest.xml.');
            }

            return ['manifest' => $manifest, 'files' => $files, 'bytes' => $bytes];
        } finally {
            $zip->close();
        }
    }

    private function isForbidden(string $name): bool {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return $extension !== '' && in_array($extension, self::FORBIDDEN_EXTENSIONS, true);
    }

    /**
     * Zielpfad prüfen: der normalisierte Pfad muss unter dem Zielordner
     * bleiben. `../` im Archiv ist kein Versehen, sondern ein Angriff.
     */
    private function safePath(string $root, string $name): string {
        $normalized = str_replace('\\', '/', $name);
        $segments = [];

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new ScormPackageException(ScormPackageException::PATH_ESCAPE, 'Das Paket enthält einen Pfad außerhalb des Zielordners.');
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            throw new ScormPackageException(ScormPackageException::EMPTY_NAME, 'Das Paket enthält einen leeren Dateinamen.');
        }

        $path = $root . '/' . implode('/', $segments);

        if (! str_starts_with($path, $root . '/')) {
            throw new ScormPackageException(ScormPackageException::PATH_ESCAPE, 'Das Paket enthält einen Pfad außerhalb des Zielordners.');
        }

        return $path;
    }

    private function realTarget(string $targetDir): string {
        $real = realpath($targetDir);

        if ($real === false) {
            throw new ScormPackageException(ScormPackageException::TARGET_UNWRITABLE, 'Der Zielordner ist nicht auflösbar.');
        }

        return rtrim($real, '/');
    }
}
