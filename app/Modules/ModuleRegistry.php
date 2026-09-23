<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModuleRegistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules;

use App\Enums\User\PermissionGroup;
use CommonToolkit\Helper\FileSystem\{File, Files, Folder};
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Register aller Manifeste (MVP-861). Discovery über `app/Modules/Manifests`,
 * optional aus `bootstrap/cache/modules.php` (`modules:cache`). Alle Abfragen
 * sind Indizes über die Manifeste; das Register hält keine eigene Wahrheit.
 */
final class ModuleRegistry {
    /** @var array<string, Manifest>|null Code → Manifest */
    private ?array $manifests = null;

    /** @var array<string, string>|null Tabelle → Code */
    private ?array $tableIndex = null;

    /** @var array<string, string>|null Routenmuster → Lizenzcode, spezifischste zuerst */
    private ?array $routeMap = null;

    public function __construct(
        private readonly string $manifestDirectory,
        private readonly string $cacheFile,
    ) {}

    /** @return array<string, Manifest> Code → Manifest, alphabetisch */
    public function all(): array {
        return $this->manifests ??= $this->load();
    }

    public function byCode(string $code): ?Manifest {
        return $this->all()[$code] ?? null;
    }

    /** Das Manifest, das Label und Beschreibung eines Lizenzcodes trägt. */
    public function byLicenseCode(string $licenseCode): ?Manifest {
        foreach ($this->all() as $manifest) {
            if ($manifest->licenseCode() === $licenseCode && $manifest->ownsLicense()) {
                return $manifest;
            }
        }

        return null;
    }

    /** @return list<Manifest> Alle Manifeste hinter einem Lizenzcode (Eigentümer zuerst). */
    public function allByLicenseCode(string $licenseCode): array {
        $out = array_values(array_filter($this->all(), static fn (Manifest $m): bool => $m->licenseCode() === $licenseCode));
        usort($out, static fn (Manifest $a, Manifest $b): int => (int) $b->ownsLicense() <=> (int) $a->ownsLicense());

        return $out;
    }

    public function byTable(string $table): ?Manifest {
        if ($this->tableIndex === null) {
            $this->tableIndex = [];
            foreach ($this->all() as $code => $manifest) {
                foreach ($manifest->tables() as $t) {
                    $this->tableIndex[$t] = $code;
                }
            }
        }
        $code = $this->tableIndex[$table] ?? null;

        return $code === null ? null : $this->all()[$code];
    }

    /** Manifest zu einem Domänenordner (`Finance`, `Club`, …). */
    public function byFolder(string $folder): ?Manifest {
        foreach ($this->all() as $manifest) {
            if (in_array($folder, $manifest->folders(), true)) {
                return $manifest;
            }
        }

        return null;
    }

    public function byPermissionGroup(PermissionGroup $group): ?Manifest {
        foreach ($this->all() as $manifest) {
            if (in_array($group, $manifest->permissionGroups(), true)) {
                return $manifest;
            }
        }

        return null;
    }

    public function byPlugin(string $pluginId): ?Manifest {
        foreach ($this->all() as $manifest) {
            if (in_array($pluginId, $manifest->plugins(), true)) {
                return $manifest;
            }
        }

        return null;
    }

    /**
     * Routenmuster → Lizenzcode für das Modul-Gate. Spezifischere (längere)
     * Muster zuerst, damit `finance.resale.*` vor `finance.*` greift.
     *
     * @return array<string, string>
     */
    public function routeMap(): array {
        if ($this->routeMap === null) {
            $map = [];
            foreach ($this->all() as $manifest) {
                $license = $manifest->licenseCode();
                if ($license === null) {
                    continue;
                }
                foreach ($manifest->routePatterns() as $pattern) {
                    $map[$pattern] = $license;
                }
            }
            uksort($map, static fn (string $a, string $b): int => strlen($b) <=> strlen($a) ?: strcmp($a, $b));
            $this->routeMap = $map;
        }

        return $this->routeMap;
    }

    /**
     * Lizenzcode, dessen Gate eine Route sperrt, oder null für Kern-Routen.
     * Die REST-API erbt die Zuordnung der gleichnamigen Web-Route (S-12).
     */
    public function moduleForRoute(?string $routeName): ?string {
        if ($routeName === null || $routeName === '') {
            return null;
        }
        $candidates = [$routeName];
        foreach (['api.legacy.', 'api.'] as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                $candidates[] = substr($routeName, strlen($prefix));
                break;
            }
        }
        foreach ($candidates as $candidate) {
            foreach ($this->routeMap() as $pattern => $module) {
                if (Str::is($pattern, $candidate)) {
                    return $module;
                }
            }
        }

        return null;
    }

    /** @return array<string, string> Lizenzcode → Label (nur Eigentümer) */
    public function labels(): array {
        $labels = [];
        foreach ($this->all() as $manifest) {
            $license = $manifest->licenseCode();
            if ($license !== null && $manifest->ownsLicense()) {
                $labels[$license] = $manifest->label();
            }
        }
        ksort($labels);

        return $labels;
    }

    /** @return array<string, string> Lizenzcode → Beschreibung */
    public function descriptions(): array {
        $out = [];
        foreach ($this->all() as $manifest) {
            $license = $manifest->licenseCode();
            if ($license !== null && $manifest->ownsLicense()) {
                $out[$license] = $manifest->description();
            }
        }

        return $out;
    }

    /**
     * Lizenz-Abhängigkeiten: abhängiger Lizenzcode → vorausgesetzte Lizenzcodes.
     * Nur lizenzierte Voraussetzungen zählen — ein Kernmodul ist immer da.
     *
     * @return array<string, list<string>>
     */
    public function licenseRequirements(): array {
        $out = [];
        foreach ($this->all() as $manifest) {
            $license = $manifest->licenseCode();
            if ($license === null) {
                continue;
            }
            foreach ($manifest->requires() as $requiredCode) {
                $required = $this->byCode($requiredCode)?->licenseCode();
                if ($required !== null && $required !== $license) {
                    $out[$license][] = $required;
                }
            }
        }
        foreach ($out as $license => $list) {
            $out[$license] = array_values(array_unique($list));
        }

        return $out;
    }

    /**
     * Sidebar-Zuordnung: Sektions-, Routen- und Gruppenschlüssel → Lizenzcode.
     *
     * @return array{sections: array<string, string>, items: array<string, string>, groups: array<string, string>}
     */
    public function navigationMaps(): array {
        $maps = ['sections' => [], 'items' => [], 'groups' => []];
        foreach ($this->all() as $manifest) {
            $license = $manifest->licenseCode();
            if ($license === null) {
                continue;
            }
            foreach ($manifest->navigation() as $kind => $keys) {
                foreach ($keys as $key) {
                    $maps[$kind][$key] = $license;
                }
            }
        }

        return $maps;
    }

    /** Schreibt die Manifestliste in die Cache-Datei. */
    public function cache(): void {
        $classes = array_map(static fn (Manifest $m): string => $m::class, array_values($this->discover()));
        $lines = ["<?php", '', '// Generiert durch `php artisan modules:cache` — nicht von Hand ändern.', 'return ['];
        foreach ($classes as $class) {
            $lines[] = "    \\{$class}::class,";
        }
        $lines[] = '];';
        File::write($this->cacheFile, implode("\n", $lines) . "\n");
        $this->flush();
    }

    public function clearCache(): void {
        if (File::isFile($this->cacheFile)) {
            File::delete($this->cacheFile);
        }
        $this->flush();
    }

    public function isCached(): bool {
        return File::isFile($this->cacheFile);
    }

    public function flush(): void {
        $this->manifests = null;
        $this->tableIndex = null;
        $this->routeMap = null;
    }

    /** @return array<string, Manifest> */
    private function load(): array {
        if (File::isFile($this->cacheFile)) {
            /** @var list<class-string<Manifest>> $classes */
            $classes = (array) require $this->cacheFile;
            $manifests = [];
            foreach ($classes as $class) {
                $manifests[] = new $class();
            }
        } else {
            $manifests = array_values($this->discover());
        }

        $byCode = [];
        foreach ($manifests as $manifest) {
            $code = $manifest->code();
            if (isset($byCode[$code])) {
                $first = $byCode[$code]::class;
                $second = $manifest::class;
                throw new InvalidArgumentException("Modulcode {$code} ist doppelt vergeben ({$first}, {$second}).");
            }
            $byCode[$code] = $manifest;
        }
        ksort($byCode);

        return $byCode;
    }

    /** @return array<string, Manifest> Klassenname → Instanz, aus dem Manifest-Ordner */
    private function discover(): array {
        if (! Folder::isDirectory($this->manifestDirectory)) {
            return [];
        }
        $out = [];
        $files = Files::get($this->manifestDirectory, recursive: false, fileTypes: ['php']);
        sort($files);
        foreach ($files as $file) {
            $class = 'App\\Modules\\Manifests\\' . basename($file, '.php');
            if (class_exists($class) && is_subclass_of($class, Manifest::class)) {
                $out[$class] = new $class();
            }
        }

        return $out;
    }
}
