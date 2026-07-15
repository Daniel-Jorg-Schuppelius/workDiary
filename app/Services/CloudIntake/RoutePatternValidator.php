<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RoutePatternValidator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\CloudIntake;

use App\Models\CloudIntake\CloudDocumentRoute;

/**
 * Pfadmuster der Ordnerregeln (Feature 080, MVP-352): `*` (ein Segment),
 * `**` (beliebig viele Segmente) und Whitelist-Variablen, die sich auf
 * VORHANDENE Objekte beziehen (nie Auto-Anlage). Pfade sind relativ zum
 * Stammordner, Trenner `/`, Vergleich case-insensitiv.
 *
 * `match()` liefert bei Treffer die extrahierten Variablenwerte — die
 * Auflösung auf Objekte (org-gescopt) übernimmt der Routing-Schritt der
 * Pipeline, nicht der Validator.
 */
class RoutePatternValidator {
    /** Erlaubte Pfadvariablen (Konzept §Datenmodell). */
    public const VARIABLES = [
        'customer_number',
        'project_number',
        'order_number',
        'asset_number',
        'contract_number',
    ];

    /**
     * Syntaxprüfung fürs Speichern/Aktivieren: leeres Muster, unbekannte
     * Variablen, doppelte Variablen und `***` sind Fehler.
     *
     * @return list<string> Fehlermeldungen (leer = gültig)
     */
    public function validatePattern(string $pattern): array {
        $errors = [];
        $pattern = trim($pattern, "/ \t");

        if ($pattern === '') {
            $errors[] = (string) __('cloud_intake.validation.pattern_empty');
        }

        if (str_contains($pattern, '***')) {
            $errors[] = (string) __('cloud_intake.validation.pattern_triple_star');
        }

        preg_match_all('/\{([^}]*)\}/', $pattern, $matches);
        $seen = [];
        foreach ($matches[1] as $variable) {
            if (! in_array($variable, self::VARIABLES, true)) {
                $errors[] = (string) __('cloud_intake.validation.unknown_variable', ['variable' => $variable]);
            }
            if (in_array($variable, $seen, true)) {
                $errors[] = (string) __('cloud_intake.validation.duplicate_variable', ['variable' => $variable]);
            }
            $seen[] = $variable;
        }

        return $errors;
    }

    /**
     * Prüft einen relativen Quellpfad gegen das Muster.
     *
     * @return array<string, string>|null Variablenwerte bei Treffer, sonst null
     */
    public function match(string $pattern, string $path): ?array {
        $regex = $this->toRegex($pattern);
        $path = ltrim($path, '/');

        if (preg_match($regex, $path, $matches) !== 1) {
            return null;
        }

        $variables = [];
        foreach ($matches as $key => $value) {
            if (is_string($key) && $value !== '') {
                $variables[$key] = $value;
            }
        }

        return $variables;
    }

    /**
     * Erste passende aktive Route nach Priorität (kleinster Wert gewinnt);
     * Typ-/Größenfilter der Route werden mitgeprüft.
     *
     * @param  iterable<int, CloudDocumentRoute>  $routes  bereits nach priority sortiert
     * @return array{route: CloudDocumentRoute, variables: array<string, string>}|null
     */
    public function firstMatch(iterable $routes, string $path, string $extension, int $size): ?array {
        foreach ($routes as $route) {
            if (! $route->active) {
                continue;
            }

            $allowed = $route->allowed_extensions;
            if (is_array($allowed) && $allowed !== [] && ! in_array(strtolower($extension), array_map('strtolower', $allowed), true)) {
                continue;
            }
            if ($route->max_file_size !== null && $size > (int) $route->max_file_size) {
                continue;
            }

            $variables = $this->match($route->path_pattern, $path);
            if ($variables !== null) {
                return ['route' => $route, 'variables' => $variables];
            }
        }

        return null;
    }

    /** Muster → anchored, case-insensitive Regex mit benannten Gruppen. */
    private function toRegex(string $pattern): string {
        $pattern = trim($pattern, "/ \t");

        // Platzhalter VOR preg_quote schützen.
        $pattern = str_replace(['**', '*'], ["\x01", "\x02"], $pattern);
        $pattern = preg_replace('/\{(' . implode('|', self::VARIABLES) . ')\}/', "\x03$1\x04", $pattern) ?? $pattern;

        $regex = preg_quote($pattern, '#');

        // `**` matcht über Segmentgrenzen (inkl. leer am Muster-Ende),
        // `*` genau ein Segment, Variablen ein Segment als benannte Gruppe.
        $regex = str_replace("\x01/", '(?:[^/]+/)*', $regex);
        $regex = str_replace("\x01", '.*', $regex);
        $regex = str_replace("\x02", '[^/]+', $regex);
        $regex = preg_replace('/\x03([a-z_]+)\x04/', '(?<$1>[^/]+)', $regex) ?? $regex;

        return '#^' . $regex . '$#iu';
    }
}
