<?php
/*
 * Created on   : Sun Nov 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DialogFormViewResponseRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Routing;

use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use Tests\TestCase;

/**
 * Gate (Pilot 2026-11-01): Ein Dialogformular mit `data-entry-form` sendet per
 * fetch und erwartet JSON (Weiterleitung, stay) oder 422. Liefert die Aktion
 * eine ganze Seite (View), lädt der Dialog-Host nur neu — die Antwort ist weg.
 * So verlor der Abo-Import seine Vorschau: kein Import, kein Eintrag. Solche
 * Formulare senden normal (wie die übrigen Import-Vorschauen) oder die Aktion
 * leitet weiter.
 */
class DialogFormViewResponseRuleTest extends TestCase {
    /** @var array<string, string> "View-Datei => Route" → Grund */
    private const ALLOWED = [];

    public function test_entry_dialog_forms_do_not_post_to_actions_that_render_a_page(): void {
        $found = [];
        $checked = 0;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($files as $file) {
            $path = (string) $file;
            if (! str_ends_with($path, '.blade.php')) {
                continue;
            }
            // Blade-Kommentare zählen nicht (sie erwähnen das Attribut, etwa als Begründung).
            $source = (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents($path));
            if (! str_contains($source, 'data-entry-form')) {
                continue;
            }
            preg_match_all("/(?::action|\\baction)=\"(?:\\{\\{\\s*)?route\\(\\s*'([a-z0-9_.\\-]+)'/i", $source, $matches);
            $relative = str_replace(str_replace('\\', '/', resource_path('views')) . '/', '', str_replace('\\', '/', $path));
            foreach (array_unique($matches[1]) as $name) {
                $checked++;
                if ($this->rendersPage($name) && ! isset(self::ALLOWED[$relative . ' => ' . $name])) {
                    $found[] = $relative . ' => ' . $name;
                }
            }
        }

        $this->assertGreaterThan(50, $checked, 'Sonde findet kaum Dialogformulare — Erkennung prüfen');
        $this->assertSame([], $found, "Dialogformular (data-entry-form) sendet an eine Aktion, die eine Seite rendert — normal senden oder weiterleiten:\n" . implode("\n", $found));
    }

    private function rendersPage(string $name): bool {
        $route = Route::getRoutes()->getByName($name);
        if ($route === null || ! array_intersect(['POST', 'PUT', 'PATCH', 'DELETE'], $route->methods())) {
            return false;
        }
        $action = $route->getActionName();
        if (! str_contains($action, '@')) {
            return false;
        }
        [$class, $method] = explode('@', $action);
        if (! method_exists($class, $method)) {
            return false;
        }
        $type = (new ReflectionMethod($class, $method))->getReturnType();
        $names = $type instanceof ReflectionNamedType ? [$type->getName()] : ($type instanceof ReflectionUnionType ? array_map(static fn ($t): string => $t instanceof ReflectionNamedType ? $t->getName() : '', $type->getTypes()) : []);
        foreach ($names as $returned) {
            if (is_a($returned, \Illuminate\Contracts\View\View::class, true)) {
                return true;
            }
        }

        return false;
    }
}
