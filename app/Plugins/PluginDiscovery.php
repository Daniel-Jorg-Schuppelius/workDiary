<?php
/*
 * Created on   : Mon Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginDiscovery.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins;

use App\Plugins\Contracts\Plugin;
use ReflectionClass;

/**
 * Ermittelt die zu ladenden Plugin-Klassen.
 *
 * Primär per Auto-Discovery: jede Klasse unter `app/Plugins/<Name>/<Name>Plugin.php`,
 * die {@see Plugin} implementiert, wird automatisch registriert — ein neues
 * Plugin braucht also keinen Eintrag mehr in config/plugins.php.
 *
 * Zusätzlich werden die in `config('plugins.classes')` explizit aufgeführten
 * Klassen berücksichtigt (Escape-Hatch für Plugins außerhalb von app/Plugins,
 * z. B. aus Composer-Paketen). Beide Quellen werden zusammengeführt und
 * dedupliziert; ungültige (nicht existente, abstrakte oder den Vertrag nicht
 * erfüllende) Klassen werden verworfen.
 */
final class PluginDiscovery {
    /**
     * Prozessweit memoisierter Filesystem-Scan (ändert sich zur Laufzeit nicht).
     *
     * @var list<class-string>|null
     */
    private static ?array $scanned = null;

    /**
     * Effektive, gültige Plugin-Klassenliste (Auto-Discovery + explizite Config).
     *
     * @return array<int, class-string<Plugin>>
     */
    public static function classes(): array {
        /** @var array<int, string> $explicit */
        $explicit = array_values(array_filter((array) config('plugins.classes', []), 'is_string'));

        $candidates = array_values(array_unique([...$explicit, ...self::scan()]));

        $valid = [];
        foreach ($candidates as $class) {
            if (class_exists($class)
                && is_subclass_of($class, Plugin::class)
                && ! (new ReflectionClass($class))->isAbstract()) {
                $valid[] = $class;

                continue;
            }
            // Nicht still verwerfen (W0b): ein Tippfehler im Klassennamen
            // wäre sonst ein lautlos fehlendes Plugin.
            \Illuminate\Support\Facades\Log::warning('Plugin candidate discarded (missing class, wrong contract or abstract)', ['class' => $class]);
        }

        return $valid;
    }

    /**
     * Durchsucht alle `app/Plugins/<Name>/<Name>Plugin.php`-Dateien nach
     * Plugin-Klassen (deterministisch sortiert). Reines Datei-Mapping — die
     * Vertragsprüfung erfolgt in {@see classes()}.
     *
     * @return list<class-string>
     */
    private static function scan(): array {
        if (self::$scanned !== null) {
            return self::$scanned;
        }

        $pattern = app_path('Plugins' . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . '*Plugin.php');

        $classes = [];
        foreach (glob($pattern) ?: [] as $file) {
            // Konvention strikt: <Name>/<Name>Plugin.php — sonst matcht der Glob
            // auch Contracts/Plugin.php (das Interface selbst) und Warnungen fluten.
            if (basename($file) !== basename(\dirname($file)) . 'Plugin.php') {
                continue;
            }
            $class = self::classFromPath($file);
            if ($class !== null) {
                $classes[] = $class;
            }
        }
        sort($classes);

        return self::$scanned = $classes;
    }

    /**
     * Leitet aus einem Dateipfad den FQCN ab (PSR-4: `app/` ⇒ `App\`).
     *
     * @return class-string|null
     */
    private static function classFromPath(string $file): ?string {
        $appPath = app_path() . DIRECTORY_SEPARATOR;
        if (! str_starts_with($file, $appPath)) {
            return null;
        }

        $relative = substr($file, \strlen($appPath), -\strlen('.php'));

        /** @var class-string $fqcn */
        $fqcn = 'App\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

        return $fqcn;
    }

    /** Test-Hook: erzwingt beim nächsten Aufruf einen frischen Filesystem-Scan. */
    public static function flush(): void {
        self::$scanned = null;
    }
}
