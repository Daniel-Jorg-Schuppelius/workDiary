<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DependencyGraph.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules;

use CommonToolkit\Helper\FileSystem\{File, Files, Folder};

/**
 * Import-Graph zwischen Modulen (MVP-863): Für jede Datei der Schichten
 * Services, Controller, Policies, Jobs, Listener, Observer und Commands wird
 * das Modul aus dem Domänenordner bestimmt und jeder `use App\Services\…`
 * einem Zielmodul zugeordnet. Contracts, Dto, Exceptions und Modelle zählen
 * nicht ({@see BoundaryRules}); Dateien in `Contracts`-Ordnern sind keine Quelle.
 */
final class DependencyGraph {
    /** @var list<string> Schicht → Domänenordner darunter */
    private const LAYERS = ['app/Services', 'app/Http/Controllers', 'app/Policies', 'app/Jobs', 'app/Listeners', 'app/Observers', 'app/Console/Commands', 'app/Plugins'];

    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly BoundaryRules $rules,
        private readonly string $basePath,
    ) {}

    /**
     * @return list<array{file: string, from: string, to: string, class: string, allowed: bool, reason: string}>
     */
    public function edges(): array {
        $edges = [];
        foreach (self::LAYERS as $layer) {
            $dir = $this->basePath . '/' . $layer;
            if (! Folder::isDirectory($dir)) {
                continue;
            }
            foreach (Files::get($dir, recursive: true, fileTypes: ['php']) as $file) {
                $relative = substr($file, strlen($this->basePath) + 1);
                $from = $this->moduleOfFile($layer, $relative);
                if ($from === null) {
                    continue;
                }
                foreach ($this->serviceImports(File::read($file)) as $class) {
                    $to = $this->moduleOfServiceClass($class);
                    if ($to === null || $to->code() === $from->code()) {
                        continue;
                    }
                    $allowed = $this->rules->allows($from, $to);
                    $edges[] = ['file' => $relative, 'from' => $from->code(), 'to' => $to->code(), 'class' => $class, 'allowed' => $allowed, 'reason' => $this->rules->explain($from, $to)];
                }
            }
        }

        return $edges;
    }

    /** @return list<string> Baseline-Schlüssel der Verstöße, sortiert */
    public function violationKeys(): array {
        $keys = [];
        foreach ($this->edges() as $edge) {
            if (! $edge['allowed']) {
                $keys[] = self::key($edge);
            }
        }
        $keys = array_values(array_unique($keys));
        sort($keys);

        return $keys;
    }

    /** @param array{file: string, from: string, to: string, class: string} $edge */
    public static function key(array $edge): string {
        return sprintf('%s→%s %s %s', $edge['from'], $edge['to'], $edge['file'], $edge['class']);
    }

    private function moduleOfFile(string $layer, string $relative): ?Manifest {
        $rest = substr($relative, strlen($layer) + 1);
        $parts = explode('/', $rest);
        if ($layer === 'app/Plugins') {
            // Nur die Plattform-Hilfen der Plugins; konkrete Plugins sind Adapter über mehrere Module.
            return $parts[0] === 'Support' ? $this->registry->byCode('integration') : null;
        }
        if (count($parts) < 2) {
            // Wurzeldateien (Jobs, Observer, Listener, Commands ohne Ordner): Modul unbekannt.
            return null;
        }
        $folder = $parts[0];
        if ($folder === 'Concerns' || $folder === 'Contracts' || in_array('Contracts', $parts, true)) {
            return null;
        }

        return $this->registry->byFolder($folder);
    }

    /** @return list<string> vollqualifizierte Service-Klassen aus use-Zeilen und Inline-FQCN im Code (ohne Contracts) */
    private function serviceImports(string $source): array {
        $classes = [];
        // Inline-Verweise (`app(\App\Services\X\Y::class)`, `\App\Services\X\Y::CONST`) — Kommentare zählen nicht.
        $code = (string) preg_replace(['~/\*.*?\*/~s', '~//[^\n]*~'], '', $source);
        if (preg_match_all('/\\\\(App\\\\Services\\\\\w+\\\\[\w\\\\]*\w)(?=::|\s|\(|\)|,|;|\$|\|)/', $code, $inline) > 0) {
            foreach ($inline[1] as $fq) {
                $classes[] = $fq;
            }
        }
        if (preg_match_all('/^use (App\\\\Services\\\\[\w\\\\]+);/m', $source, $m) > 0) {
            foreach ($m[1] as $fq) {
                $classes[] = $fq;
            }
        }
        if (preg_match_all('/^use (App\\\\Services(?:\\\\[\w\\\\]+)?)\\\\\{([^}]+)\};/m', $source, $g, PREG_SET_ORDER) > 0) {
            foreach ($g as $grp) {
                foreach (explode(',', $grp[2]) as $part) {
                    $part = trim(explode(' as ', trim($part))[0]);
                    if ($part !== '') {
                        $classes[] = $grp[1] . '\\' . $part;
                    }
                }
            }
        }

        return array_values(array_filter($classes, static fn (string $fq): bool => ! str_contains($fq, '\\Contracts\\')
            && ! str_contains($fq, '\\Dto\\')
            && ! str_contains($fq, '\\Exceptions\\')
            && ! str_starts_with($fq, 'App\\Services\\Concerns\\')));
    }

    private function moduleOfServiceClass(string $class): ?Manifest {
        $parts = explode('\\', substr($class, strlen('App\\Services\\')));

        return count($parts) >= 2 ? $this->registry->byFolder($parts[0]) : null;
    }
}
