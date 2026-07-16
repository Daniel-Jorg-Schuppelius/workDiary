<?php
/*
 * Created on   : Wed Jul 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NavigationGoldenTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Navigation;

use App\Models\{Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Golden-Snapshot der sichtbaren Navigation (Feature 081, MVP-372).
 *
 * Friert die gerenderte Navigation (Sidebar-Sektionen + alle Nav-Links in
 * Header und Sidebar) je Persona (Plan × Rolle) als Fixture ein. Der Umbau
 * auf die NavigationRegistry darf die Sichtbarkeit für unkonfigurierte
 * Bestands-Organisationen NICHT verändern — jede Abweichung schlägt hier auf.
 *
 * Erstlauf ohne Fixture: schreibt tests/Fixtures/navigation-golden.json
 * (self-recording) und besteht. Danach wird strikt verglichen. Zum bewussten
 * Neu-Aufnehmen (z. B. neuer Menüpunkt) Fixture löschen und Test 1× laufen
 * lassen — die Änderung ist dann im Diff der Fixture reviewbar.
 *
 * Baseline (2026-07-15): nach dem NavigationRegistry-Umbau (MVP-372)
 * aufgenommen. Die einzigen bewussten Deltas gegenüber der hart codierten
 * Vor-Version sind (a) die neuen Feature-081-Einträge „Menü anpassen“
 * (`me.navigation.customize`), „Alle Funktionen“ (`me.functions`) und
 * „Funktionsumfang“ (`admin.scope`, nur Org-Admin) sowie (b) die
 * Vereinheitlichung des System-Menü-Gatings über NavGate — dadurch wird
 * z. B. `admin.surcharge-rules` im free-Plan korrekt versteckt (die Route
 * hängt an `module.lohn`), statt nur inline nach Recht geprüft zu werden.
 */
class NavigationGoldenTest extends TestCase {
    use RefreshDatabase;

    private const FIXTURE = 'tests/Fixtures/navigation-golden.json';

    public function test_navigation_matches_golden_snapshot(): void {
        $snapshot = [];

        // $this->be() je Persona überschreibt den aktiven Nutzer; ein
        // explizites logout() ist nicht nötig.
        foreach ($this->personas() as $name => [, $user]) {
            $this->be($user);
            // Frischer Resolver-Zustand je Persona (FeatureFlagResolver cached pro Request).
            app(\App\Services\Licensing\FeatureFlagResolver::class)->flush();

            $response = $this->get(route('dashboard'));
            $response->assertOk();
            $html = $response->getContent();

            // Feature 082: aktiver Arbeitsbereich „finance" schränkt die
            // Sidebar-Sektionen rein kosmetisch ein — je Persona eingefroren,
            // damit Änderungen an der Fokus-Filterung sichtbar werden.
            $focusHtml = $this->withSession([\App\Services\Navigation\NavFocusService::SESSION_KEY => 'finance'])
                ->get(route('dashboard'))->getContent();

            $snapshot[$name] = [
                'sections' => $this->sectionKeys($html),
                'sidebar' => $this->hrefs($this->between($html, '<aside id="app-sidebar"', '</aside>')),
                'header' => $this->hrefs($this->between($html, '<header id="app-header"', '</header>')),
                'sections_focus_finance' => $this->sectionKeys($this->between($focusHtml, '<aside id="app-sidebar"', '</aside>')),
            ];
        }

        $path = base_path(self::FIXTURE);
        if (! is_file($path)) {
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0775, true);
            }
            file_put_contents($path, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
            $this->assertFileExists($path);

            return;
        }

        /** @var array<string, array<string, list<string>>> $golden */
        $golden = json_decode((string) file_get_contents($path), true);

        foreach ($snapshot as $persona => $areas) {
            foreach ($areas as $area => $values) {
                $this->assertSame(
                    $golden[$persona][$area] ?? [],
                    $values,
                    "Navigation weicht vom Golden-Snapshot ab: Persona '{$persona}', Bereich '{$area}'.",
                );
            }
        }
    }

    /**
     * @return array<string, array{0: Organization, 1: User}>
     */
    private function personas(): array {
        $enterprise = Organization::factory()->create(['name' => 'Golden Enterprise']);
        $free = Organization::factory()->free()->create(['name' => 'Golden Free']);

        return [
            'enterprise_admin' => [$enterprise, User::factory()->admin()->create(['organization_id' => $enterprise->id])],
            'enterprise_user' => [$enterprise, User::factory()->user()->create(['organization_id' => $enterprise->id])],
            'free_admin' => [$free, User::factory()->admin()->create(['organization_id' => $free->id])],
        ];
    }

    /** @return list<string> */
    private function sectionKeys(string $html): array {
        preg_match_all('/data-sidebar-section-key="([^"]+)"/', $html, $m);

        return array_values(array_unique($m[1]));
    }

    private function between(string $html, string $start, string $end): string {
        $from = strpos($html, $start);
        if ($from === false) {
            return '';
        }
        $to = strpos($html, $end, $from);

        return $to === false ? substr($html, $from) : substr($html, $from, $to - $from);
    }

    /**
     * Alle Link-Ziele eines HTML-Ausschnitts, normalisiert auf den Pfad:
     * Host + Query entfernt, numerische Segmente (IDs variieren mit der
     * Testreihenfolge) durch {n} ersetzt. Sortiert + dedupliziert.
     *
     * @return list<string>
     */
    private function hrefs(string $html): array {
        preg_match_all('/href="([^"]+)"/', $html, $m);

        $paths = [];
        foreach ($m[1] as $href) {
            if ($href === '#' || str_starts_with($href, 'javascript:')) {
                continue;
            }
            $path = (string) (parse_url(html_entity_decode($href), PHP_URL_PATH) ?? '');
            if ($path === '') {
                continue;
            }
            $paths[] = (string) preg_replace('#/\d+(?=/|$)#', '/{n}', $path);
        }

        $paths = array_values(array_unique($paths));
        sort($paths);

        return $paths;
    }
}
