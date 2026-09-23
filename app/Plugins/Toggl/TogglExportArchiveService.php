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
use CommonToolkit\Helper\FileSystem\{File, Files, Folder};
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
     * via realpath-Eingrenzung (Toolkit). Verhindert das Auslesen beliebiger
     * Server-Verzeichnisse; relative Pfade gelten relativ zur jeweiligen Basis.
     */
    public function safeImportPath(string $path): ?string {
        // Nur der EIGENE Import-Ordner (S-56) — plus der global konfigurierte
        // Export-Pfad, der bewusst geteilt ist (Betreiber legt ihn fest).
        $bases = array_filter([
            (string) config('plugins.toggl.export_path', ''),
            storage_path('app/toggl-imports/' . $this->organizationFolder()),
        ]);
        foreach ($bases as $base) {
            $resolved = Folder::resolveWithin((string) $base, $path, allowBase: true);
            if ($resolved !== null && Folder::exists($resolved)) {
                return $resolved;
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
        $target = $base . '/' . now()->format('Ymd_His') . '_' . Str::random(8);
        try {
            Folder::create($base, 0775, true);
            $this->pruneOldImports($base);
            Folder::create($target, 0775, true);
        } catch (\Throwable) {
            throw new TogglArchiveException((string) __('ZIP konnte nicht entpackt werden.'));
        }

        if (! ZipFile::isZipFile($archivePath)) {
            try {
                Folder::delete($target, true); // symlink-sicher: Links weg, Ziele bleiben
            } catch (\Throwable) {
                // Best effort — Reste räumt pruneOldImports() beim nächsten Upload ab.
            }

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
            try {
                Folder::delete($target, true); // symlink-sicher: Links weg, Ziele bleiben
            } catch (\Throwable) {
                // Best effort — Reste räumt pruneOldImports() beim nächsten Upload ab.
            }

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
            if (File::isFile($dir . '/projects.json')) {
                $wrap = $dir . '/Workspace';
                $items = [...Files::get($dir), ...Folder::get($dir)];
                try {
                    Folder::create($wrap, 0775, true);
                    foreach ($items as $item) {
                        if ($item !== $wrap && basename($item) !== 'Workspace') {
                            File::rename($item, $wrap . '/' . basename($item));
                        }
                    }
                } catch (\Throwable) {
                    // Best effort: detectWorkspaces() meldet einen unvollständigen Export.
                }

                return $dir;
            }

            // Sichtbare Unterordner (wie glob('*')): versteckte zählen nicht als Wrapper.
            $subdirs = array_values(array_filter(Folder::get($dir), static fn(string $sub): bool => ! str_starts_with(basename($sub), '.')));
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
        $cutoff = now()->subDay()->getTimestamp();
        foreach (Folder::get($base) as $dir) {
            try {
                if (Folder::modifiedTime($dir) < $cutoff) {
                    Folder::delete($dir, true); // symlink-sicher: Links weg, Ziele bleiben
                }
            } catch (\Throwable) {
                // Best effort — der nächste Upload versucht es erneut.
            }
        }
    }

    /** Unterordner je Organisation — ohne gebundene Org ein neutraler Platz. */
    private function organizationFolder(): string {
        $organization = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        $id = $organization instanceof \App\Models\Platform\Organization ? (int) $organization->id : 0;

        return 'org-' . $id;
    }
}
