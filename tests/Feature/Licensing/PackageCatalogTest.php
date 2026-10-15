<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PackageCatalogTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Licensing;

use App\Services\Licensing\{ModuleScopeService, PackageCatalog};
use Tests\TestCase;

/**
 * Produktpakete (Feature 165, MVP-1187): kumulative Auflösung, Konsistenz
 * gegen Manifeste, Arbeitsbereiche und Startseiten, Paket-Presets.
 */
class PackageCatalogTest extends TestCase {
    public function test_packages_are_cumulative_and_consistent(): void {
        $catalog = app(PackageCatalog::class);

        $this->assertSame([], $catalog->problems());

        $start = $catalog->package('start');
        $team = $catalog->package('team');
        $betrieb = $catalog->package('betrieb');
        $this->assertNotNull($start);
        $this->assertNotNull($team);
        $this->assertNotNull($betrieb);

        // Kunden, Projekte, Angebote und Rechnungen hängen an module.vertrieb (B1).
        $this->assertSame(['module.vertrieb'], $start['modules']);
        $this->assertContains('module.vertrieb', $team['modules']);
        $this->assertContains('module.planung', $team['modules']);
        $this->assertContains('module.auswertungen_team', $betrieb['modules']);
        $this->assertContains('module.finance', $betrieb['modules']);

        // Betriebsmodule sind lizenziert, aber nicht Teil der Einstiegsvorgabe.
        $this->assertNotContains('module.finance', $betrieb['default_active']);
        $this->assertContains('module.planung', $betrieb['default_active']);

        // Kompakte Bereiche summieren sich; Rollen-Vorschläge des Vorgängers bleiben.
        $this->assertSame(['compact_day', 'compact_orders'], $start['workspaces']);
        $this->assertSame(['compact_day', 'compact_orders', 'compact_team'], $team['workspaces']);
        $this->assertSame('compact_day', $team['focus_roles']['user']);
        $this->assertSame('compact_team', $team['focus_roles']['teamleitung']);
        $this->assertSame('today.show', $betrieb['startpages']['user']);
        $this->assertSame('compact_billing', $betrieb['commercial_workspace']);
    }

    public function test_modules_for_resolves_packages_and_bundles(): void {
        $catalog = app(PackageCatalog::class);

        $this->assertSame(['module.vertrieb', 'module.planung', 'module.auswertungen_team'], $catalog->modulesFor('team'));
        $this->assertSame(['module.fuhrpark', 'module.spesen'], $catalog->modulesFor('fuhrpark_reisen'));
        $this->assertSame([], $catalog->modulesFor('gibt_es_nicht'));

        $containing = array_map(static fn (array $row): string => $row['kind'] . ':' . $row['key'], $catalog->containing('module.planung'));
        $this->assertSame(['package:team', 'package:betrieb'], $containing);
    }

    public function test_problems_report_unknown_codes_missing_requirements_and_dangling_references(): void {
        config()->set('plans.packages', [
            'kaputt' => [
                'label' => 'Kaputt',
                'includes' => 'fehlt',
                'modules' => ['module.gibt_es_nicht', 'module.service_desk'],
                'default_active' => ['module.lager'],
                'workspaces' => ['kein_bereich'],
                'focus_roles' => ['niemand' => 'compact_day'],
                'startpages' => ['user' => 'keine.route'],
            ],
        ]);
        config()->set('plans.bundles', ['leer' => ['label' => 'Leer', 'modules' => [], 'workspace' => 'nirgends']]);

        $problems = app(PackageCatalog::class)->problems();

        $this->assertContains("Paket kaputt: includes 'fehlt' ist kein Paket.", $problems);
        $this->assertContains("Paket kaputt: unbekannter Modulcode 'module.gibt_es_nicht'.", $problems);
        $this->assertContains("Paket kaputt: 'module.service_desk' setzt 'module.helpdesk' voraus, das Paket enthält es nicht.", $problems);
        $this->assertContains("Paket kaputt: default_active 'module.lager' ist nicht lizenziert.", $problems);
        $this->assertContains("Paket kaputt: Arbeitsbereich 'kein_bereich' fehlt in config/navigation_focus.php.", $problems);
        $this->assertContains("Paket kaputt: focus_roles nennt unbekannte Rolle 'niemand'.", $problems);
        $this->assertContains("Paket kaputt: Startseite 'keine.route' fehlt in personalization.startpages.", $problems);
        $this->assertContains('Bündel leer: keine Module.', $problems);
        $this->assertContains("Bündel leer: Arbeitsbereich 'nirgends' fehlt in config/navigation_focus.php.", $problems);
    }

    public function test_cyclic_includes_are_reported_without_recursion(): void {
        config()->set('plans.packages', [
            'a' => ['label' => 'A', 'includes' => 'b', 'modules' => ['module.vertrieb']],
            'b' => ['label' => 'B', 'includes' => 'a', 'modules' => ['module.planung']],
        ]);

        $catalog = app(PackageCatalog::class);
        $problems = $catalog->problems();

        $this->assertContains('Paket a: includes-Kette ist zyklisch.', $problems);
        $this->assertContains('Paket b: includes-Kette ist zyklisch.', $problems);
        // Die Auflösung bricht den Zyklus und liefert beide Module.
        $this->assertEqualsCanonicalizing(['module.vertrieb', 'module.planung'], $catalog->modulesFor('a'));
    }

    public function test_package_presets_are_appended_to_scope_presets(): void {
        $presets = app(ModuleScopeService::class)->presets();

        $this->assertArrayHasKey('schlank', $presets);
        $this->assertArrayHasKey('start', $presets);
        $this->assertSame(['module.vertrieb'], $presets['start']['modules']);
        $this->assertSame('start', $presets['start']['package']);
        $this->assertNull($presets['schlank']['package']);
        $this->assertSame(['module.vertrieb', 'module.planung', 'module.auswertungen_team'], $presets['betrieb']['modules']);
    }

    public function test_modules_check_reports_package_problems(): void {
        config()->set('plans.packages', ['kaputt' => ['label' => 'Kaputt', 'modules' => ['module.gibt_es_nicht']]]);

        $this->artisan('modules:check')
            ->expectsOutputToContain("Paketkatalog: Paket kaputt: unbekannter Modulcode 'module.gibt_es_nicht'.")
            ->assertExitCode(1);
    }
}
