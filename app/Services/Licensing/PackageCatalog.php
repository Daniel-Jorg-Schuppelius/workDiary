<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PackageCatalog.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Licensing;

use App\Enums\User\UserRole;
use App\Modules\ModuleRegistry;

/**
 * Produktpakete und Fachbündel (Feature 165, MVP-1187).
 *
 * Liest `plans.packages` und `plans.bundles`, löst `includes` kumulativ auf
 * und liefert je Paket Lizenzmodule, Einstiegsvorgaben und Version. Der
 * Katalog schaltet nichts frei: Die Ausstellung schreibt `modulesFor()` in
 * die signierten `addons`, der Resolver kennt nur Lizenz und Overrides.
 *
 * @phpstan-type Package array{key: string, label: string, description: string, version: int, includes: ?string, modules: list<string>, default_active: list<string>, workspaces: list<string>, optional_workspaces: list<string>, commercial_workspace: ?string, focus_roles: array<string, string>, startpages: array<string, string>}
 * @phpstan-type Bundle array{key: string, label: string, description: string, modules: list<string>, workspace: ?string}
 */
class PackageCatalog {
    /** @var array<string, Package>|null */
    private ?array $packages = null;

    public function __construct(
        private readonly ModuleCatalog $catalog,
        private readonly ModuleRegistry $modules,
    ) {}

    /**
     * Alle Pakete in Konfigurationsreihenfolge, kumulativ aufgelöst.
     *
     * @return array<string, Package>
     */
    public function packages(): array {
        if ($this->packages !== null) {
            return $this->packages;
        }

        $raw = $this->rawPackages();
        $out = [];
        foreach (array_keys($raw) as $key) {
            $out[$key] = $this->resolve($key, $raw, []);
        }

        return $this->packages = $out;
    }

    public function has(string $key): bool {
        return array_key_exists($key, $this->packages());
    }

    /** @return Package|null */
    public function package(string $key): ?array {
        return $this->packages()[$key] ?? null;
    }

    /**
     * Fachbündel in Konfigurationsreihenfolge.
     *
     * @return array<string, Bundle>
     */
    public function bundles(): array {
        $out = [];
        foreach ((array) config('plans.bundles', []) as $key => $bundle) {
            if (! is_string($key) || $key === '' || ! is_array($bundle)) {
                continue;
            }
            $out[$key] = [
                'key' => $key,
                'label' => (string) ($bundle['label'] ?? $key),
                'description' => (string) ($bundle['description'] ?? ''),
                'modules' => $this->strings($bundle['modules'] ?? []),
                'workspace' => isset($bundle['workspace']) && is_string($bundle['workspace']) && $bundle['workspace'] !== '' ? $bundle['workspace'] : null,
            ];
        }

        return $out;
    }

    public function hasBundle(string $key): bool {
        return array_key_exists($key, $this->bundles());
    }

    /**
     * Lizenzmodule eines Pakets (kumulativ) oder Bündels; leer bei unbekanntem Schlüssel.
     *
     * @return list<string>
     */
    public function modulesFor(string $key): array {
        $package = $this->package($key);
        if ($package !== null) {
            return $package['modules'];
        }

        return $this->bundles()[$key]['modules'] ?? [];
    }

    /**
     * Pakete und Bündel, die ein Modul enthalten (für Erweiterung und Katalog).
     *
     * @return list<array{kind: 'package'|'bundle', key: string, label: string}>
     */
    public function containing(string $module): array {
        $out = [];
        foreach ($this->packages() as $key => $package) {
            if (in_array($module, $package['modules'], true)) {
                $out[] = ['kind' => 'package', 'key' => $key, 'label' => $package['label']];
            }
        }
        foreach ($this->bundles() as $key => $bundle) {
            if (in_array($module, $bundle['modules'], true)) {
                $out[] = ['kind' => 'bundle', 'key' => $key, 'label' => $bundle['label']];
            }
        }

        return $out;
    }

    /**
     * Konsistenzprobleme des Katalogs für `modules:check`: unbekannte Codes,
     * unerfüllte Modulabhängigkeiten, unbekannte Vorgänger, Arbeitsbereiche,
     * Rollen und Startseiten.
     *
     * @return list<string>
     */
    public function problems(): array {
        $problems = [];
        $raw = $this->rawPackages();
        $focuses = array_keys((array) config('navigation_focus.focuses', []));
        $startpages = array_keys((array) config('personalization.startpages', []));
        $roles = array_map(static fn (UserRole $r): string => $r->value, UserRole::cases());
        $requirements = $this->modules->licenseRequirements();

        foreach ($raw as $key => $definition) {
            $includes = $definition['includes'] ?? null;
            if ($includes !== null && ! isset($raw[$includes])) {
                $problems[] = "Paket {$key}: includes '{$includes}' ist kein Paket.";
            }
            if ($this->hasCycle((string) $key, $raw)) {
                $problems[] = "Paket {$key}: includes-Kette ist zyklisch.";

                continue;
            }
            $package = $this->resolve((string) $key, $raw, []);
            foreach ($package['modules'] as $module) {
                if (! $this->catalog->has($module)) {
                    $problems[] = "Paket {$key}: unbekannter Modulcode '{$module}'.";
                }
                foreach ($requirements[$module] ?? [] as $required) {
                    if (! in_array($required, $package['modules'], true)) {
                        $problems[] = "Paket {$key}: '{$module}' setzt '{$required}' voraus, das Paket enthält es nicht.";
                    }
                }
            }
            foreach ($package['default_active'] as $module) {
                if (! in_array($module, $package['modules'], true)) {
                    $problems[] = "Paket {$key}: default_active '{$module}' ist nicht lizenziert.";
                }
            }
            $allWorkspaces = array_merge($package['workspaces'], $package['optional_workspaces'], $package['commercial_workspace'] !== null ? [$package['commercial_workspace']] : []);
            foreach ($allWorkspaces as $workspace) {
                if (! in_array($workspace, $focuses, true)) {
                    $problems[] = "Paket {$key}: Arbeitsbereich '{$workspace}' fehlt in config/navigation_focus.php.";
                }
            }
            foreach ($package['focus_roles'] as $role => $workspace) {
                if (! in_array($role, $roles, true)) {
                    $problems[] = "Paket {$key}: focus_roles nennt unbekannte Rolle '{$role}'.";
                }
                if (! in_array($workspace, array_merge($allWorkspaces, ['all']), true)) {
                    $problems[] = "Paket {$key}: focus_roles '{$role}' zeigt auf nicht angebotenen Bereich '{$workspace}'.";
                }
            }
            foreach ($package['startpages'] as $role => $route) {
                if (! in_array($role, $roles, true)) {
                    $problems[] = "Paket {$key}: startpages nennt unbekannte Rolle '{$role}'.";
                }
                if (! in_array($route, $startpages, true)) {
                    $problems[] = "Paket {$key}: Startseite '{$route}' fehlt in personalization.startpages.";
                }
            }
        }

        foreach ($this->bundles() as $key => $bundle) {
            if ($bundle['modules'] === []) {
                $problems[] = "Bündel {$key}: keine Module.";
            }
            foreach ($bundle['modules'] as $module) {
                if (! $this->catalog->has($module)) {
                    $problems[] = "Bündel {$key}: unbekannter Modulcode '{$module}'.";
                }
            }
            if ($bundle['workspace'] !== null && ! in_array($bundle['workspace'], $focuses, true)) {
                $problems[] = "Bündel {$key}: Arbeitsbereich '{$bundle['workspace']}' fehlt in config/navigation_focus.php.";
            }
        }

        return $problems;
    }

    /** @return array<string, array<string, mixed>> */
    private function rawPackages(): array {
        $out = [];
        foreach ((array) config('plans.packages', []) as $key => $definition) {
            if (is_string($key) && $key !== '' && is_array($definition)) {
                $out[$key] = $definition;
            }
        }

        return $out;
    }

    /**
     * Löst ein Paket kumulativ auf (Vorgänger zuerst, eigene Werte danach).
     *
     * @param  array<string, array<string, mixed>>  $raw
     * @param  list<string>  $seen
     * @return Package
     */
    private function resolve(string $key, array $raw, array $seen): array {
        $definition = $raw[$key] ?? [];
        $includes = isset($definition['includes']) && is_string($definition['includes']) && $definition['includes'] !== '' ? $definition['includes'] : null;

        $base = [
            'modules' => [],
            'default_active' => [],
            'workspaces' => [],
            'optional_workspaces' => [],
            'commercial_workspace' => null,
            'focus_roles' => [],
            'startpages' => [],
        ];
        if ($includes !== null && isset($raw[$includes]) && ! in_array($includes, $seen, true)) {
            $base = $this->resolve($includes, $raw, [...$seen, $key]);
        }

        $commercial = $definition['commercial_workspace'] ?? $base['commercial_workspace'];

        return [
            'key' => $key,
            'label' => (string) ($definition['label'] ?? $key),
            'description' => (string) ($definition['description'] ?? ''),
            'version' => max(1, (int) ($definition['version'] ?? 1)),
            'includes' => $includes,
            'modules' => $this->union($base['modules'], $this->strings($definition['modules'] ?? [])),
            'default_active' => $this->union($base['default_active'], $this->strings($definition['default_active'] ?? [])),
            'workspaces' => $this->union($base['workspaces'], $this->strings($definition['workspaces'] ?? [])),
            'optional_workspaces' => $this->union($base['optional_workspaces'], $this->strings($definition['optional_workspaces'] ?? [])),
            'commercial_workspace' => is_string($commercial) && $commercial !== '' ? $commercial : null,
            'focus_roles' => array_merge($base['focus_roles'], $this->stringMap($definition['focus_roles'] ?? [])),
            'startpages' => array_merge($base['startpages'], $this->stringMap($definition['startpages'] ?? [])),
        ];
    }

    /** @param array<string, array<string, mixed>> $raw */
    private function hasCycle(string $key, array $raw): bool {
        $seen = [];
        $current = $key;
        while ($current !== null) {
            if (in_array($current, $seen, true)) {
                return true;
            }
            $seen[] = $current;
            $next = $raw[$current]['includes'] ?? null;
            $current = is_string($next) && $next !== '' ? $next : null;
        }

        return false;
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return list<string>
     */
    private function union(array $a, array $b): array {
        return array_values(array_unique(array_merge($a, $b)));
    }

    /** @return list<string> */
    private function strings(mixed $values): array {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn ($v): string => is_scalar($v) ? (string) $v : '', $values), static fn (string $v): bool => $v !== ''));
    }

    /** @return array<string, string> */
    private function stringMap(mixed $values): array {
        if (! is_array($values)) {
            return [];
        }
        $out = [];
        foreach ($values as $k => $v) {
            if (is_string($k) && $k !== '' && is_string($v) && $v !== '') {
                $out[$k] = $v;
            }
        }

        return $out;
    }
}
