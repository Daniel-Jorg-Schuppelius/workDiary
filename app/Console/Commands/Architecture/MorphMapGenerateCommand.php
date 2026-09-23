<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MorphMapGenerateCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Architecture;

use App\Support\Architecture\ModelScanner;
use CommonToolkit\Helper\FileSystem\File;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Erzeugt config/morph-map.php (MVP-860): `aliases` vollständig neu aus den
 * Modellen (Alias = Tabellenname, fremde Verbindung mit Präfix), `legacy` nur
 * wachsend — bestehende Einträge bleiben, weil die Datenbank sie enthält.
 * `--init` friert einmalig alle heutigen Klassennamen als Legacy-Namen ein,
 * `--check` schreibt nichts und meldet Abweichungen (Gate/CI).
 */
class MorphMapGenerateCommand extends Command {
    protected $signature = 'morph-map:generate {--init : Alle heutigen Klassennamen als Legacy-Namen einfrieren (nur beim ersten Lauf)} {--check : Nur prüfen, ob die Datei aktuell ist}';

    protected $description = 'Erzeugt config/morph-map.php aus den Eloquent-Modellen (Alias = Tabellenname).';

    public function handle(): int {
        $path = config_path('morph-map.php');
        /** @var array{aliases?: array<string, class-string>, legacy?: array<string, class-string>} $current */
        $current = File::isFile($path) ? (array) require $path : [];
        $legacy = $current['legacy'] ?? [];

        $aliases = [];
        $collisions = [];
        foreach ($this->modelClasses() as $class) {
            $alias = $this->aliasFor($class);
            if (isset($aliases[$alias])) {
                $collisions[] = sprintf('%s: %s und %s', $alias, $aliases[$alias], $class);
                continue;
            }
            $aliases[$alias] = $class;
        }
        if ($collisions !== []) {
            $this->error('Zwei Modelle auf einer Tabelle — Alias nicht eindeutig:');
            foreach ($collisions as $line) {
                $this->line('  ' . $line);
            }

            return self::FAILURE;
        }
        ksort($aliases, SORT_STRING);

        if ($this->option('init')) {
            if ($legacy !== []) {
                $this->error('Legacy-Namen sind bereits eingefroren; --init nur beim ersten Lauf.');

                return self::FAILURE;
            }
            foreach ($aliases as $class) {
                $legacy[$class] = $class;
            }
            ksort($legacy, SORT_STRING);
        }

        $dangling = array_filter($legacy, static fn (string $class): bool => ! class_exists($class));
        if ($dangling !== []) {
            $this->error('Legacy-Einträge zeigen auf fehlende Klassen (Umzug ohne Nachziehen der Map?):');
            foreach ($dangling as $name => $class) {
                $this->line("  {$name} => {$class}");
            }

            return self::FAILURE;
        }

        $content = $this->render($aliases, $legacy);
        $existing = File::isFile($path) ? File::read($path) : '';

        if ($this->option('check')) {
            if ($existing !== $content) {
                $this->error('config/morph-map.php ist nicht aktuell — `php artisan morph-map:generate` ausführen.');

                return self::FAILURE;
            }
            $this->info(sprintf('config/morph-map.php aktuell: %d Aliase, %d Legacy-Namen.', count($aliases), count($legacy)));

            return self::SUCCESS;
        }

        if ($existing === $content) {
            $this->info('config/morph-map.php unverändert.');

            return self::SUCCESS;
        }
        File::write($path, $content);
        $this->info(sprintf('config/morph-map.php geschrieben: %d Aliase, %d Legacy-Namen.', count($aliases), count($legacy)));

        return self::SUCCESS;
    }

    /** @return list<class-string<Model>> Projekt-Modelle plus die Fremdmodelle, die als Morph-Ziel dienen. */
    private function modelClasses(): array {
        $classes = ModelScanner::classes();
        foreach ([config('permission.models.role'), config('permission.models.permission')] as $vendor) {
            if (is_string($vendor) && is_subclass_of($vendor, Model::class) && ! in_array($vendor, $classes, true)) {
                $classes[] = $vendor;
            }
        }
        sort($classes);

        return $classes;
    }

    /** @param class-string<Model> $class */
    private function aliasFor(string $class): string {
        $model = new $class();
        $connection = $model->getConnectionName();
        $default = (string) config('database.default');

        return ($connection === null || $connection === $default ? '' : $connection . '.') . $model->getTable();
    }

    /**
     * @param array<string, class-string> $aliases
     * @param array<string, class-string> $legacy
     */
    private function render(array $aliases, array $legacy): string {
        $lines = [
            '<?php',
            '/*',
            ' * Created on   : Wed Sep 23 2026',
            ' * Author       : Daniel Jörg Schuppelius',
            ' * Author Uri   : https://schuppelius.org',
            ' * Filename     : morph-map.php',
            ' * License      : AGPL-3.0-or-later',
            ' * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html',
            ' */',
            '',
            'declare(strict_types=1);',
            '',
            '/*',
            ' * GENERIERT durch `php artisan morph-map:generate` — nicht von Hand ändern.',
            ' *',
            ' * aliases: Alias (Tabellenname) → Modellklasse; Wert aller `*_type`-Spalten.',
            ' * legacy:  bisheriger Klassenname → Modellklasse; nur wachsend, nie löschen.',
            ' *          Zieht eine Klasse um, bleibt der Schlüssel und der Wert wird',
            ' *          nachgeführt — Hash-Ketten und Sqids hängen daran (App\Support\MorphMap).',
            ' */',
            'return [',
            "    'aliases' => [",
        ];
        foreach ($aliases as $alias => $class) {
            $lines[] = sprintf("        '%s' => \\%s::class,", $alias, $class);
        }
        $lines[] = '    ],';
        $lines[] = "    'legacy' => [";
        foreach ($legacy as $name => $class) {
            $lines[] = sprintf("        '%s' => \\%s::class,", str_replace('\\', '\\\\', $name), $class);
        }
        $lines[] = '    ],';
        $lines[] = '];';

        return implode("\n", $lines) . "\n";
    }
}
