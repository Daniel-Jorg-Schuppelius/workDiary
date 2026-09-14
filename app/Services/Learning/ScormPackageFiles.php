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

use App\Models\Learning\LearningScormPackage;

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
    public static function absolutePath(LearningScormPackage $package, string $path): ?string {
        $base = storage_path('app/' . $package->storage_path);
        $target = $path !== '' ? $base . '/' . $path : $base . '/' . (string) $package->launch_href;

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
     */
    public static function contentSecurityPolicy(?string $appOrigin = null): string {
        $ancestors = "'self'" . ($appOrigin !== null ? ' ' . $appOrigin : '');

        return "default-src 'self' data: blob:; script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
            . "style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; media-src 'self' data: blob:; "
            . "font-src 'self' data:; connect-src 'self'; frame-src 'self'; frame-ancestors {$ancestors}; "
            . "form-action 'none'; base-uri 'none'";
    }
}
