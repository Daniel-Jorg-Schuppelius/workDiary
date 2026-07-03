<?php
/*
 * Created on   : Thu Jul 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Filename.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Support;

/**
 * Sichere Ablage von Upload-Originalnamen (`original_name`).
 *
 * Bewusst app-lokal statt CommonToolkit `File::sanitizeFilename`:
 * Das Toolkit normalisiert aggressiv auf ASCII (Umlaute/Leerzeichen → `_`,
 * Extension-Strip, Unterstrich-Kollaps) — hier müssen anzeigefähige
 * Originalnamen inkl. Umlauten/Leerzeichen erhalten bleiben; nur
 * Pfadanteile, Steuerzeichen und Traversal-Zeichen werden entschärft.
 */
final class Filename {
    /**
     * Entfernt Verzeichnis-Anteile, ersetzt Null-Bytes, Steuerzeichen
     * sowie Slashes/Backslashes durch `_` und begrenzt auf 255 Zeichen.
     */
    public static function sanitize(string $name): string {
        $name = basename($name);
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]/', '_', $name) ?? 'file';

        return mb_substr($name, 0, 255);
    }
}
