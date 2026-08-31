<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglExportArchiveService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Toggl;

use App\Plugins\Toggl\Sources\TogglWorkspaceReader;
use CommonToolkit\Helper\FileSystem\FileTypes\ZipFile;
use Illuminate\Support\Str;

/**
 * Kapselt das Datei-/ZIP-Handling des Toggl-Workspace-Export-Imports:
 * Pfad-Absicherung, sicheres Entpacken hochgeladener Export-ZIPs und die
 * Ermittlung des tatsächlichen Export-Wurzelordners. Aus dem TogglController
 * extrahiert (Refactoring Welle 2, B6c).
 */
class TogglExportArchiveService {
    /**
     * Obergrenzen fürs Entpacken (S-56) — großzügig, aber endlich. Durchgereicht
     * an `ZipFile::extract()`, das seit common-toolkit v1.31.5 gegen die
     * deklarierte UND die tatsächliche Größe prüft.
     */
    private const MAX_ENTRIES = 20000;

    private const MAX_TOTAL_BYTES = 2 * 1024 * 1024 * 1024;

    private const MAX_RATIO = 200;

    /**
     * Beschränkt einen vom Admin angegebenen Import-Pfad auf erlaubte
     * Basisverzeichnisse (konfigurierter Toggl-Export-Pfad + storage/app/toggl-imports)
     * via realpath-Prefix. Verhindert das Auslesen beliebiger Server-Verzeichnisse.
     */
    public function safeImportPath(string $path): ?string {
        if (trim($path) === '') {
            return null;
        }
        $real = realpath($path);
        if ($real === false || ! is_dir($real)) {
            return null;
        }
        // Nur der EIGENE Import-Ordner (S-56) — plus der global konfigurierte
        // Export-Pfad, der bewusst geteilt ist (Betreiber legt ihn fest).
        $bases = array_filter([
            (string) config('plugins.toggl.export_path', ''),
            storage_path('app/toggl-imports/' . $this->organizationFolder()),
        ]);
        foreach ($bases as $base) {
            $realBase = realpath((string) $base);
            if ($realBase !== false && ($real === $realBase || str_starts_with($real, $realBase . DIRECTORY_SEPARATOR))) {
                return $real;
            }
        }

        return null;
    }

    /**
     * Entpackt eine hochgeladene Toggl-Export-ZIP sicher nach
     * storage/app/toggl-imports/<id>/ und liefert den erkannten
     * Export-Wurzelordner zurück.
     *
     * Zip-Slip-Schutz über das Toolkit: jeder Eintrag wird gegen das
     * normalisierte Zielverzeichnis (realpath-Containment) geprüft — strenger
     * als ein „..“-/Wurzel-String-Check. Die hochgeladene Temp-Datei wird nicht
     * gelöscht (Laravel räumt sie selbst auf).
     *
     * @throws TogglArchiveException  bei ungültiger bzw. nicht entpackbarer ZIP
     */
    public function extractUpload(string $archivePath): string {
        // Import-Ordner je Organisation (Sicherheitsscan 2026-08-23, S-56):
        // vorher lagen die Entpackordner aller Mandanten nebeneinander, und
        // `safeImportPath()` ließ jeden davon als Quelle zu — ein Org-Admin
        // konnte den Export eines anderen Mandanten einlesen.
        $base = storage_path('app/toggl-imports/' . $this->organizationFolder());
        if (! is_dir($base)) {
            @mkdir($base, 0775, true);
        }
        $this->pruneOldImports($base);

        $target = $base . '/' . now()->format('Ymd_His') . '_' . Str::random(8);
        @mkdir($target, 0775, true);

        if (! ZipFile::isZipFile($archivePath)) {
            $this->rrmdir($target);

            throw new TogglArchiveException((string) __('Keine gültige ZIP-Datei.'));
        }

        try {
            ZipFile::extract(
                $archivePath,
                $target,
                deleteSourceFile: false,
                maxEntries: self::MAX_ENTRIES,
                maxBytes: self::MAX_TOTAL_BYTES,
                maxRatio: self::MAX_RATIO,
            );
        } catch (\Throwable $e) {
            $this->rrmdir($target);

            throw new TogglArchiveException((string) __('ZIP konnte nicht entpackt werden.'));
        }

        return $this->resolveExportRoot($target);
    }

    /**
     * Findet den tatsächlichen Export-Wurzelordner im entpackten ZIP:
     *  - durchläuft transparente „Wrapper"-Ordner (genau ein Unterordner),
     *  - und packt einen flachen Single-Workspace-Export (projects.json direkt
     *    im Ordner, keine Unterordner) in einen benannten Unterordner, damit
     *    {@see TogglWorkspaceReader::detectWorkspaces()} ihn erkennt.
     */
    private function resolveExportRoot(string $dir): string {
        for ($depth = 0; $depth < 6; $depth++) {
            if (TogglWorkspaceReader::detectWorkspaces($dir) !== []) {
                return $dir;
            }

            // Flacher Single-Workspace-Export → in Unterordner „Workspace" heben.
            if (is_file($dir . '/projects.json')) {
                $wrap = $dir . '/Workspace';
                @mkdir($wrap, 0775, true);
                foreach ((array) glob($dir . '/*') as $item) {
                    if ($item === $wrap) {
                        continue;
                    }
                    @rename((string) $item, $wrap . '/' . basename((string) $item));
                }

                return $dir;
            }

            $subdirs = array_values(array_filter((array) glob($dir . '/*', GLOB_ONLYDIR)));
            if (count($subdirs) === 1) {
                $dir = $subdirs[0];

                continue;
            }
            break;
        }

        return $dir;
    }

    /** Entfernt entpackte Import-Ordner, die älter als einen Tag sind (Best-Effort). */
    private function pruneOldImports(string $base): void {
        foreach ((array) glob($base . '/*', GLOB_ONLYDIR) as $dir) {
            if (is_string($dir) && @filemtime($dir) !== false && filemtime($dir) < now()->subDay()->getTimestamp()) {
                $this->rrmdir($dir);
            }
        }
    }

    /** Rekursives Löschen eines Verzeichnisses (Best-Effort). */
    private function rrmdir(string $dir): void {
        if (! is_dir($dir)) {
            return;
        }
        foreach ((array) scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    /** Unterordner je Organisation — ohne gebundene Org ein neutraler Platz. */
    private function organizationFolder(): string {
        $organization = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        $id = $organization instanceof \App\Models\Organization ? (int) $organization->id : 0;

        return 'org-' . $id;
    }
}
