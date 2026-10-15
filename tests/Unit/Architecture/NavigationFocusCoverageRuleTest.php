<?php
/*
 * Created on   : Tue Sep 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NavigationFocusCoverageRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Arbeitsbereiche müssen jede Sidebar-Sektion erreichen (Feature 082).
 *
 * Ein Arbeitsbereich ist ein reiner Anzeigefilter: Er schaltet nichts frei,
 * blendet aber alles aus, was er nicht kennt. Eine neue Sektion, die in
 * `config/navigation_focus.php` fehlt, ist deshalb für **jeden** Nutzer
 * unsichtbar, der irgendeinen Bereich außer „Alles anzeigen" gewählt hat —
 * und zwar lautlos: kein Fehler, kein Hinweis, das Menü ist einfach kürzer.
 *
 * Genau so war die **Lernplattform** nach ihrer Fertigstellung nicht zu
 * finden (2026-09-01); mit ihr zehn weitere Sektionen aus jüngeren Wellen.
 * Diese Regel ersetzt das Erinnern: Wer eine Sektion anlegt, ordnet sie zu
 * oder begründet die Ausnahme hier.
 *
 * Abgedeckt gilt eine Sektion auch dann, wenn ein Arbeitsbereich eine ihrer
 * **Gruppen** nennt (`group:sales-crm` zeigt `section:sales` im Ausschnitt).
 */
class NavigationFocusCoverageRuleTest extends TestCase {
    use ScansSourceTree;

    public function test_jede_sidebar_sektion_liegt_in_einem_arbeitsbereich(): void {
        $sections = $this->sectionKeys();
        $this->assertNotEmpty($sections, 'Keine Sektionen gefunden — die Regex passt nicht mehr zur Registry.');

        $covered = $this->coveredKeys();

        $missing = [];
        foreach ($sections as $section) {
            if (in_array('section:' . $section, $covered, true)) {
                continue;
            }
            // Eine Gruppe der Sektion genügt: der Ausschnitt bleibt sichtbar.
            $viaGroup = false;
            foreach ($covered as $ref) {
                if (str_starts_with($ref, 'group:' . $section . '-')) {
                    $viaGroup = true;

                    break;
                }
            }
            if (! $viaGroup) {
                $missing[] = $section;
            }
        }

        $this->assertSame([], $missing, sprintf(
            "Diese Sidebar-Sektionen stehen in keinem Arbeitsbereich und sind damit für jeden Fokus-Nutzer unsichtbar:\n  %s\n\n"
            . 'Zuordnen in config/navigation_focus.php. Es gibt bewusst KEINE Ausnahmeliste: '
            . 'eine Sektion, die kein Arbeitsbereich zeigt, ist für Fokus-Nutzer schlicht nicht vorhanden. '
            . 'Soll das doch einmal gewollt sein, gehört die Ausnahme hier mit Begründung ergänzt.',
            implode("\n  ", $missing),
        ));
    }

    /**
     * Kein Arbeitsbereich darf auf eine Sektion, Gruppe, Route oder
     * Schnellaktion zeigen, die es in der Registry nicht gibt (`item:` und
     * `create:` seit Feature 165 für die kompakten Bereiche).
     */
    public function test_kein_arbeitsbereich_zeigt_ins_leere(): void {
        $sections = $this->sectionKeys();
        $groups = $this->groupKeys();
        $routes = $this->routeKeys();

        $dangling = [];
        foreach (array_merge($this->coveredKeys(), $this->createKeys()) as $ref) {
            [$type, $key] = array_pad(explode(':', $ref, 2), 2, null);
            $pool = match ($type) {
                'section' => $sections,
                'group' => $groups,
                'item', 'create' => $routes,
                default => [],
            };
            if (! in_array((string) $key, $pool, true)) {
                $dangling[] = $ref;
            }
        }

        $this->assertSame([], $dangling, "Verweise auf nicht existierende Navigationsschlüssel:\n  " . implode("\n  ", $dangling));
    }

    /** Kompakte Bereiche (Feature 165) bleiben klein: höchstens sechs Ziele, drei Schnellaktionen. */
    public function test_kompakte_bereiche_bleiben_klein(): void {
        /** @var array{focuses?: array<string, array<string, mixed>>} $config */
        $config = require $this->repoRoot() . '/config/navigation_focus.php';
        $violations = [];
        foreach ((array) ($config['focuses'] ?? []) as $key => $focus) {
            if (! str_starts_with((string) $key, 'compact_')) {
                continue;
            }
            $keys = (array) ($focus['keys'] ?? []);
            $create = (array) ($focus['create'] ?? []);
            if (count($keys) > 6 || $keys === []) {
                $violations[] = "{$key}: " . count($keys) . ' Ziele (erlaubt 1–6)';
            }
            foreach ($keys as $ref) {
                if (! str_starts_with((string) $ref, 'item:')) {
                    $violations[] = "{$key}: '{$ref}' ist kein Einzelziel (item:)";
                }
            }
            if (count($create) > 3) {
                $violations[] = "{$key}: " . count($create) . ' Schnellaktionen (erlaubt 0–3)';
            }
        }

        $this->assertSame([], $violations, "Kompakte Arbeitsbereiche verletzen die Zielwerte aus Feature 165 §1:\n  " . implode("\n  ", $violations));
    }

    /** @return list<string> Alle Routen der Registry (Sidebar und Schnellerstellung). */
    private function routeKeys(): array {
        $source = (string) file_get_contents($this->repoRoot() . '/app/Services/Navigation/NavigationRegistry.php');
        preg_match_all("/'route'\\s*=>\\s*'([a-z0-9._-]+)'/", $source, $m);

        // Die Arbeitsliste steht als Platzhalter `$indexRoute` im Bauplan;
        // `build()` setzt dort 'duties.index' ein.
        return array_values(array_unique([...$m[1], 'duties.index']));
    }

    /** @return list<string> */
    private function createKeys(): array {
        /** @var array{focuses?: array<string, array<string, mixed>>} $config */
        $config = require $this->repoRoot() . '/config/navigation_focus.php';
        $refs = [];
        foreach ((array) ($config['focuses'] ?? []) as $focus) {
            foreach ((array) ($focus['create'] ?? []) as $ref) {
                $refs[] = (string) $ref;
            }
        }

        return array_values(array_unique($refs));
    }

    /** @return list<string> */
    private function sectionKeys(): array {
        $source = (string) file_get_contents($this->repoRoot() . '/app/Services/Navigation/NavigationRegistry.php');
        preg_match_all("/\\\$sidebarSections\\[\\]\\s*=\\s*\\[\\s*\\n\\s*'key'\\s*=>\\s*'([a-z0-9-]+)'/", $source, $m);

        return array_values(array_unique($m[1]));
    }

    /** @return list<string> */
    private function groupKeys(): array {
        $source = (string) file_get_contents($this->repoRoot() . '/app/Services/Navigation/NavigationRegistry.php');
        preg_match_all("/'key'\\s*=>\\s*'([a-z0-9-]+)'/", $source, $m);

        return array_values(array_diff(array_unique($m[1]), $this->sectionKeys()));
    }

    /**
     * @return list<string>
     *
     * Die Konfiguration wird direkt geladen: Architektur-Gates laufen ohne
     * Laravel-Container, `config()` gaebe es hier nicht.
     */
    private function coveredKeys(): array {
        /** @var array{focuses?: array<string, array<string, mixed>>} $config */
        $config = require $this->repoRoot() . '/config/navigation_focus.php';
        /** @var array<string, array<string, mixed>> $focuses */
        $focuses = (array) ($config['focuses'] ?? []);

        $refs = [];
        foreach ($focuses as $focus) {
            foreach ((array) ($focus['keys'] ?? []) as $ref) {
                $refs[] = (string) $ref;
            }
        }

        return array_values(array_unique($refs));
    }
}
