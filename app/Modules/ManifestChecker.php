<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManifestChecker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules;

use App\Enums\User\PermissionGroup;
use App\Support\Architecture\SchemaDump;
use CommonToolkit\Helper\FileSystem\Folder;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Vollständigkeitsprüfung der Manifeste (MVP-861) — eine Regelmenge für
 * `modules:check` und das Gate `ModuleManifestCoverageRuleTest`:
 *
 * a) jede Schema-Tabelle gehört genau einem Manifest,
 * b) jeder Lizenzcode aus `plans.tiers` hat genau einen Eigentümer und jedes
 *    lizenzierte Manifest steht in einem Tarif,
 * c) jedes Routenmuster trifft mindestens eine registrierte Route,
 * d) `requires()` zeigt auf existierende Codes und ist zyklenfrei,
 * e) jeder Domänenordner der Schichten gehört genau einem Manifest,
 * f) jede Rechtegruppe gehört genau einem Manifest,
 * g) Navigations- und Plugin-Schlüssel sind eindeutig.
 */
final class ManifestChecker {
    /** @var list<string> Schichtordner, deren Unterordner Domänen sind */
    private const LAYERS = ['app/Models', 'app/Services', 'app/Http/Controllers', 'app/Policies', 'app/Http/Requests', 'database/factories'];

    /**
     * Technische Unterordner, die keine Domäne sind — Name → Begründung.
     *
     * @var array<string, string>
     */
    public const FOLDER_EXCEPTIONS = [
        'Concerns' => 'Traits, keine Domäne.',
        'Contracts' => 'Interfaces, keine Domäne.',
        'Scopes' => 'Query-Scopes, keine Domäne.',
        'Api' => 'HTTP-Schnitt nach Zielgruppe (REST), Domäne ergibt sich aus dem Routennamen.',
        'Admin' => 'HTTP-Schnitt nach Zielgruppe (Administration).',
        'Account' => 'HTTP-Schnitt nach Zielgruppe (eigenes Konto).',
        'Me' => 'HTTP-Schnitt nach Zielgruppe (persönlicher Bereich, Feature 082).',
        'Install' => 'Installer, läuft vor jedem Modul.',
        'Careers' => 'Öffentliche Bewerberseiten (sessionlos), Teil von Applications.',
    ];

    public function __construct(private readonly ModuleRegistry $registry, private readonly string $basePath) {}

    /** @return list<string> Verstöße, leer = alles zugeordnet */
    public function check(): array {
        return [
            ...$this->checkTables(),
            ...$this->checkLicenses(),
            ...$this->checkRoutePatterns(),
            ...$this->checkRequires(),
            ...$this->checkFolders(),
            ...$this->checkPermissionGroups(),
            ...$this->checkUniqueKeys(),
            ...$this->checkWiring(),
        ];
    }

    /**
     * Erweiterungspunkte, Contracts, Bindungen und Listener (MVP-863): jede
     * genannte Klasse existiert und erfüllt ihr Interface; ein Contract wird
     * höchstens einmal gebunden und ist von genau einem Modul definiert.
     *
     * @return list<string>
     */
    private function checkWiring(): array {
        $out = [];
        $defined = [];
        $bound = [];
        foreach ($this->registry->all() as $code => $manifest) {
            foreach ($manifest->extensions() as $interface => $classes) {
                if (! interface_exists($interface)) {
                    $out[] = "Manifest {$code}: Erweiterungspunkt {$interface} ist kein Interface.";

                    continue;
                }
                foreach ($classes as $class) {
                    if (! class_exists($class) || ! is_subclass_of($class, $interface)) {
                        $out[] = "Manifest {$code}: {$class} implementiert den Erweiterungspunkt {$interface} nicht.";
                    }
                }
            }
            foreach ($manifest->contracts() as $contract => $null) {
                $defined[$contract][] = $code;
                if (! interface_exists($contract)) {
                    $out[] = "Manifest {$code}: Contract {$contract} ist kein Interface.";
                } elseif (! class_exists($null) || ! is_subclass_of($null, $contract)) {
                    $out[] = "Manifest {$code}: Null-Implementierung {$null} erfüllt {$contract} nicht.";
                }
            }
            foreach ($manifest->bindings() as $contract => $implementation) {
                $bound[$contract][] = $code;
                if (! interface_exists($contract) || ! class_exists($implementation) || ! is_subclass_of($implementation, $contract)) {
                    $out[] = "Manifest {$code}: Bindung {$implementation} erfüllt {$contract} nicht.";
                }
            }
            foreach ($manifest->listeners() as $event => $listeners) {
                if (! class_exists($event)) {
                    $out[] = "Manifest {$code}: Event {$event} existiert nicht.";
                }
                foreach ($listeners as $listener) {
                    if (! class_exists($listener) || ! method_exists($listener, 'handle')) {
                        $out[] = "Manifest {$code}: Listener {$listener} existiert nicht oder hat kein handle().";

                        continue;
                    }
                    // Die Event-Discovery registriert über den Typ des ersten handle()-Parameters —
                    // der muss das deklarierte Event nennen, sonst läuft der Listener nie.
                    if (class_exists($event) && ! $this->handles($listener, $event)) {
                        $out[] = "Manifest {$code}: Listener {$listener} nimmt {$event} nicht in handle() entgegen.";
                    }
                }
            }
        }
        foreach ($defined as $contract => $codes) {
            if (count($codes) > 1) {
                $out[] = "Contract {$contract} ist mehrfach definiert: " . implode(', ', $codes) . '.';
            }
        }
        foreach ($bound as $contract => $codes) {
            if (count($codes) > 1) {
                $out[] = "Contract {$contract} ist mehrfach gebunden: " . implode(', ', $codes) . '.';
            }
            if (! isset($defined[$contract])) {
                $out[] = "Contract {$contract} wird von " . implode(', ', $codes) . " gebunden, aber kein Manifest definiert ihn (contracts()).";
            }
        }

        return $out;
    }

    /** @return list<string> */
    private function checkTables(): array {
        $owners = [];
        foreach ($this->registry->all() as $code => $manifest) {
            foreach ($manifest->tables() as $table) {
                $owners[$table][] = $code;
            }
        }
        $out = [];
        $schema = SchemaDump::tableNames($this->basePath . '/database/schema/mysql-schema.sql');
        foreach ($schema as $table) {
            $list = $owners[$table] ?? [];
            if ($list === []) {
                $out[] = "Tabelle {$table} gehört keinem Manifest.";
            } elseif (count($list) > 1) {
                $out[] = "Tabelle {$table} gehört mehreren Manifesten: " . implode(', ', $list) . '.';
            }
        }
        $known = array_fill_keys($schema, true);
        foreach ($owners as $table => $codes) {
            if (! isset($known[$table])) {
                $out[] = "Manifest " . implode(', ', $codes) . " nennt die Tabelle {$table}, die es im Schema nicht gibt.";
            }
        }

        return $out;
    }

    /** @return list<string> */
    private function checkLicenses(): array {
        $out = [];
        $owners = [];
        foreach ($this->registry->all() as $code => $manifest) {
            $license = $manifest->licenseCode();
            if ($license === null) {
                continue;
            }
            if (! str_starts_with($license, 'module.')) {
                $out[] = "Manifest {$code}: Lizenzcode {$license} beginnt nicht mit module.";
            }
            if ($manifest->ownsLicense()) {
                $owners[$license][] = $code;
            }
        }
        $tiered = [];
        foreach ((array) config('plans.tiers', []) as $codes) {
            foreach ((array) $codes as $license) {
                // Technische Flags (protocols.signed) sind keine Module.
                if (str_starts_with((string) $license, 'module.')) {
                    $tiered[(string) $license] = true;
                }
            }
        }
        foreach (array_keys($tiered) as $license) {
            $list = $owners[$license] ?? [];
            if ($list === []) {
                $out[] = "Lizenzcode {$license} aus plans.tiers hat kein Manifest.";
            } elseif (count($list) > 1) {
                $out[] = "Lizenzcode {$license} hat mehrere Eigentümer: " . implode(', ', $list) . '.';
            }
        }
        foreach ($owners as $license => $list) {
            if (! isset($tiered[$license])) {
                $out[] = "Lizenzcode {$license} (Manifest " . implode(', ', $list) . ") steht in keinem Tarif von plans.tiers.";
            }
        }

        return $out;
    }

    /** @return list<string> */
    private function checkRoutePatterns(): array {
        $names = [];
        /** @var RoutingRoute $route */
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = $route->getName();
            if ($name !== null) {
                $names[] = $name;
            }
        }
        $out = [];
        foreach ($this->registry->all() as $code => $manifest) {
            foreach ($manifest->routePatterns() as $pattern) {
                $hit = false;
                foreach ($names as $name) {
                    if (Str::is($pattern, $name)) {
                        $hit = true;
                        break;
                    }
                }
                if (! $hit) {
                    $out[] = "Manifest {$code}: Routenmuster {$pattern} trifft keine registrierte Route.";
                }
            }
        }

        return $out;
    }

    /** @return list<string> */
    private function checkRequires(): array {
        $out = [];
        $all = $this->registry->all();
        foreach ($all as $code => $manifest) {
            foreach ($manifest->requires() as $required) {
                if (! isset($all[$required])) {
                    $out[] = "Manifest {$code} setzt unbekanntes Modul {$required} voraus.";
                } elseif ($required === $code) {
                    $out[] = "Manifest {$code} setzt sich selbst voraus.";
                }
            }
        }
        foreach ($all as $code => $manifest) {
            $seen = [$code => true];
            $stack = $manifest->requires();
            while ($stack !== []) {
                $next = array_pop($stack);
                if (isset($seen[$next])) {
                    if ($next === $code) {
                        $out[] = "Manifest {$code}: zyklische Abhängigkeit über requires().";
                    }
                    continue;
                }
                $seen[$next] = true;
                foreach (isset($all[$next]) ? $all[$next]->requires() : [] as $r) {
                    $stack[] = $r;
                }
            }
        }

        return $out;
    }

    /** @return list<string> */
    private function checkFolders(): array {
        $out = [];
        $claims = [];
        foreach ($this->registry->all() as $code => $manifest) {
            foreach ($manifest->folders() as $folder) {
                $claims[$folder][] = $code;
            }
        }
        foreach ($claims as $folder => $codes) {
            if (count($codes) > 1) {
                $out[] = "Ordner {$folder} wird von mehreren Manifesten beansprucht: " . implode(', ', $codes) . '.';
            }
        }
        foreach (self::LAYERS as $layer) {
            $dir = $this->basePath . '/' . $layer;
            if (! Folder::isDirectory($dir)) {
                continue;
            }
            foreach (Folder::get($dir) as $entry) {
                $name = basename($entry);
                if (! Folder::isDirectory($entry) || isset(self::FOLDER_EXCEPTIONS[$name]) || isset($claims[$name])) {
                    continue;
                }
                $out[] = "Ordner {$layer}/{$name} gehört keinem Manifest.";
            }
        }

        return $out;
    }

    /** @return list<string> */
    private function checkPermissionGroups(): array {
        $owners = [];
        foreach ($this->registry->all() as $code => $manifest) {
            foreach ($manifest->permissionGroups() as $group) {
                $owners[$group->value][] = $code;
            }
        }
        $out = [];
        foreach (PermissionGroup::cases() as $group) {
            $list = $owners[$group->value] ?? [];
            if ($list === []) {
                $out[] = "Rechtegruppe {$group->value} gehört keinem Manifest.";
            } elseif (count($list) > 1) {
                $out[] = "Rechtegruppe {$group->value} gehört mehreren Manifesten: " . implode(', ', $list) . '.';
            }
        }

        return $out;
    }

    /** @return list<string> */
    private function checkUniqueKeys(): array {
        $out = [];
        $nav = ['sections' => [], 'items' => [], 'groups' => []];
        $plugins = [];
        foreach ($this->registry->all() as $code => $manifest) {
            foreach ($manifest->navigation() as $kind => $keys) {
                foreach ($keys as $key) {
                    $nav[$kind][$key][] = $code;
                }
            }
            foreach ($manifest->plugins() as $plugin) {
                $plugins[$plugin][] = $code;
            }
        }
        foreach ($nav as $kind => $keys) {
            foreach ($keys as $key => $codes) {
                if (count($codes) > 1) {
                    $out[] = "Navigation ({$kind}) {$key} ist mehrfach zugeordnet: " . implode(', ', $codes) . '.';
                }
            }
        }
        foreach ($plugins as $plugin => $codes) {
            if (count($codes) > 1) {
                $out[] = "Plugin {$plugin} ist mehrfach zugeordnet: " . implode(', ', $codes) . '.';
            }
        }

        return $out;
    }

    /** @param class-string $listener @param class-string $event */
    private function handles(string $listener, string $event): bool {
        $parameter = (new \ReflectionMethod($listener, 'handle'))->getParameters()[0] ?? null;
        $type = $parameter?->getType();
        $names = match (true) {
            $type instanceof \ReflectionNamedType => [$type->getName()],
            $type instanceof \ReflectionUnionType => array_map(static fn (\ReflectionNamedType $t): string => $t->getName(), array_filter($type->getTypes(), static fn ($t): bool => $t instanceof \ReflectionNamedType)),
            default => [],
        };
        foreach ($names as $name) {
            if ($name === $event || is_subclass_of($event, $name)) {
                return true;
            }
        }

        return false;
    }
}
