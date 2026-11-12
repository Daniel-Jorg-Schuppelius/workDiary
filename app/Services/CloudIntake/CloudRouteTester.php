<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloudRouteTester.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\CloudIntake;

use App\Enums\CloudIntake\CloudIntakeRouteTarget;
use App\Enums\Document\DocumentType;
use App\Models\CloudIntake\{CloudDocumentConnection, CloudDocumentRoute};

/**
 * Regeltest einer Ordnerregel (Feature 080 §cloud_document_routes, MVP-1613):
 * Trifft der Testpfad das Muster, welche Variablen werden aufgelöst und
 * welches Ziel entsteht? Eine aktive Regel darf sich mit keiner anderen
 * aktiven Regel derselben Verbindung überschneiden — erkannt werden gleiche
 * Muster und Testpfade, die mehrere Regeln treffen (eine vollständige
 * Schnittmenge zweier Glob-Muster ist nicht entscheidbar billig).
 */
class CloudRouteTester {
    public function __construct(
        private readonly RoutePatternValidator $patterns,
        private readonly CloudIntakeRouter $router,
        private readonly CloudRouteTargets $targets,
    ) {}

    public function test(CloudDocumentConnection $connection, CloudDocumentRoute $route, string $testPath): CloudRouteTestResult {
        $path = $this->relativePath($connection, $testPath);
        $lines = [(string) __('cloud_intake.test.path', ['path' => $path])];
        if ($path === '') {
            return $this->fail('test_path', (string) __('cloud_intake.test.empty'), $lines);
        }

        $extension = $this->extension($path);
        if (! $this->allowsExtension($route, $extension)) {
            return $this->fail('test_path', (string) __('cloud_intake.test.extension_blocked', ['extension' => $extension !== '' ? $extension : '—']), $lines);
        }
        $variables = $this->patterns->match($route->path_pattern, $path);
        if ($variables === null) {
            return $this->fail('test_path', (string) __('cloud_intake.test.no_match'), $lines);
        }
        $lines[] = $variables === []
            ? (string) __('cloud_intake.test.no_variables')
            : (string) __('cloud_intake.test.variables', ['list' => implode(', ', array_map(static fn (string $k, string $v): string => '{' . $k . '} = ' . $v, array_keys($variables), $variables))]);

        if ($route->active && ($overlap = $this->overlap($connection, $route, $path, $extension)) !== null) {
            return $this->fail('active', $overlap, $lines);
        }

        return $this->target($route, $variables, $lines);
    }

    /**
     * @param  array<string, string>  $variables
     * @param  list<string>  $lines
     */
    private function target(CloudDocumentRoute $route, array $variables, array $lines): CloudRouteTestResult {
        if (! $this->router->targetAvailable($route->target)) {
            return $this->fail('target', (string) __('cloud_intake.test.target_unavailable', ['target' => $route->target->label()]), $lines);
        }
        if ($route->target !== CloudIntakeRouteTarget::Document) {
            return $this->pass((string) __('cloud_intake.test.target_area', ['target' => $route->target->label()]), $lines);
        }

        $type = (DocumentType::tryFrom((string) $route->document_type) ?? DocumentType::Other)->label();
        if ($route->target_ref_type !== null && $route->target_ref_id !== null) {
            $fixed = $this->targets->resolveStored($route->target_ref_type, (int) $route->target_ref_id);

            return $fixed !== null
                ? $this->pass((string) __('cloud_intake.test.target_document', ['type' => $type, 'target' => $this->targets->describe($fixed)]), $lines)
                : $this->fail('target_ref', (string) __('cloud_intake.test.target_ref_missing'), $lines);
        }
        if ($variables === []) {
            return $this->pass((string) __('cloud_intake.test.target_free', ['type' => $type]), $lines);
        }
        if (count($variables) > 1) {
            return $this->fail('path_pattern', (string) __('cloud_intake.test.ambiguous'), $lines);
        }

        $variable = (string) array_key_first($variables);
        $value = trim((string) reset($variables));
        $found = $this->router->lookup($variable, $value);

        return $found !== null
            ? $this->pass((string) __('cloud_intake.test.target_document', ['type' => $type, 'target' => $this->targets->describe($found)]), $lines)
            : $this->fail('test_path', (string) __('cloud_intake.test.unresolved', ['variable' => $variable, 'value' => $value]), $lines);
    }

    /** Meldung zur ersten Überschneidung mit einer anderen aktiven Regel, sonst null. */
    private function overlap(CloudDocumentConnection $connection, CloudDocumentRoute $route, string $path, string $extension): ?string {
        $others = $connection->routes()
            ->where('active', true)
            ->when($route->exists, fn ($q) => $q->whereKeyNot($route->getKey()))
            ->orderBy('priority')
            ->get();

        foreach ($others as $other) {
            if (! $this->extensionsIntersect($route, $other)) {
                continue;
            }
            $params = ['pattern' => $other->path_pattern, 'priority' => $other->priority];
            if ($this->normalize($other->path_pattern) === $this->normalize($route->path_pattern)) {
                return (string) __('cloud_intake.test.overlap_same_pattern', $params);
            }
            if ($this->allowsExtension($other, $extension) && $this->patterns->match($other->path_pattern, $path) !== null) {
                return (string) __('cloud_intake.test.overlap_test_path', $params);
            }
            $otherPath = $other->test_path !== null ? $this->relativePath($connection, $other->test_path) : '';
            if ($otherPath !== '' && $this->allowsExtension($route, $this->extension($otherPath)) && $this->patterns->match($route->path_pattern, $otherPath) !== null) {
                return (string) __('cloud_intake.test.overlap_other_test_path', $params + ['path' => $otherPath]);
            }
        }

        return null;
    }

    /** Testpfad relativ zum Stammordner — ein vollständiger Pfad wird um den Stammordner gekürzt. */
    private function relativePath(CloudDocumentConnection $connection, string $testPath): string {
        $path = '/' . trim(str_replace('\\', '/', trim($testPath)), '/');
        $root = '/' . trim((string) $connection->root_folder_path, '/');
        if ($root !== '/' && str_starts_with(mb_strtolower($path) . '/', mb_strtolower($root) . '/')) {
            $path = mb_substr($path, mb_strlen($root));
        }

        return trim($path, '/');
    }

    private function extension(string $path): string {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }

    private function allowsExtension(CloudDocumentRoute $route, string $extension): bool {
        $allowed = $route->allowed_extensions;

        return ! is_array($allowed) || $allowed === [] || in_array($extension, array_map('strtolower', $allowed), true);
    }

    private function extensionsIntersect(CloudDocumentRoute $a, CloudDocumentRoute $b): bool {
        $left = is_array($a->allowed_extensions) ? array_map('strtolower', $a->allowed_extensions) : [];
        $right = is_array($b->allowed_extensions) ? array_map('strtolower', $b->allowed_extensions) : [];

        return $left === [] || $right === [] || array_intersect($left, $right) !== [];
    }

    /** Variablen sind für die Überschneidung gleichwertig mit `*`. */
    private function normalize(string $pattern): string {
        return mb_strtolower((string) preg_replace('/\{[a-z_]+\}/', '*', trim($pattern, "/ \t")));
    }

    /** @param list<string> $lines */
    private function pass(string $summary, array $lines): CloudRouteTestResult {
        $lines[] = $summary;

        return new CloudRouteTestResult(true, (string) __('cloud_intake.test.ok', ['result' => $summary]), $lines);
    }

    /** @param list<string> $lines */
    private function fail(string $field, string $summary, array $lines): CloudRouteTestResult {
        $lines[] = $summary;

        return new CloudRouteTestResult(false, $summary, $lines, $field);
    }
}
