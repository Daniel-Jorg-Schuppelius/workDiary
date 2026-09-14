<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScormPackageFiles.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\Learning\{LearningCmi5Package, LearningScormPackage};

/**
 * Dateien eines entpackten SCORM-Pakets ausliefern — gemeinsam für den gleichen
 * Ursprung und den eigenen Inhalts-Host.
 */
final class ScormPackageFiles {
    /**
     * Absoluter Pfad einer Paketdatei, oder `null`.
     *
     * Der Pfad kommt aus dem Inhalt selbst. Er zählt nur, wenn das aufgelöste Ziel
     * wieder im Paketordner liegt und eine Datei ist.
     */
    public static function absolutePath(LearningScormPackage|LearningCmi5Package $package, string $path): ?string {
        // cmi5-Kurse ohne Paket (nur externe AUs) haben keine Dateien.
        if ($package->storage_path === null) {
            return null;
        }

        $base = storage_path('app/' . $package->storage_path);
        // Ohne Pfad nur bei SCORM die Startdatei; eine cmi5-AU nennt ihre Datei immer selbst.
        $default = $package instanceof LearningScormPackage ? (string) $package->launch_href : '';

        if ($path === '' && $default === '') {
            return null;
        }

        $target = $base . '/' . ($path !== '' ? $path : $default);

        $real = realpath($target);
        $realBase = realpath($base);

        if ($real === false || $realBase === false || ! str_starts_with($real, $realBase . DIRECTORY_SEPARATOR) || ! is_file($real)) {
            return null;
        }

        return $real;
    }

    /**
     * Eigene, enge CSP für Paketdateien: Der Inhalt darf inline skripten (fast jedes
     * Autorenwerkzeug erzeugt das), aber nichts nach außen sprechen.
     *
     * @param  string|null  $appOrigin  am Inhalts-Host der Ursprung der einbettenden Anwendung
     * @param  bool  $connectToApp  cmi5: die AU spricht per fetch mit dem LRS der Anwendung
     */
    public static function contentSecurityPolicy(?string $appOrigin = null, bool $connectToApp = false): string {
        $ancestors = "'self'" . ($appOrigin !== null ? ' ' . $appOrigin : '');
        $connect = "'self'" . ($connectToApp && $appOrigin !== null ? ' ' . $appOrigin : '');

        return "default-src 'self' data: blob:; script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
            . "style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; media-src 'self' data: blob:; "
            . "font-src 'self' data:; connect-src {$connect}; frame-src 'self'; frame-ancestors {$ancestors}; "
            . "form-action 'none'; base-uri 'none'";
    }
}
