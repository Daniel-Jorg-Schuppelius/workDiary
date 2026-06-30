<?php
/*
 * Created on   : Mon Jun 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Trans.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Lang;

/**
 * Übersetzung mit Fallback: liefert die Übersetzung zu `$key`, falls vorhanden,
 * sonst den unveränderten Rohwert. Für technische Codes (Fremdsystem-Typen,
 * Aktions-Verben), die je nach Plugin/Version um neue Werte wachsen können —
 * unbekannte Werte werden so roh, aber lesbar angezeigt statt als Roh-Key.
 */
final class Trans {
    public static function or(string $key, ?string $fallback = null): string {
        if (Lang::has($key)) {
            return (string) __($key);
        }

        return (string) ($fallback ?? $key);
    }
}
