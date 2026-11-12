<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GuestHomePageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Platform;

use App\Enums\Modules\ModuleKind;
use App\Http\Controllers\Platform\HomeController;
use App\Modules\{Manifest, ModuleRegistry};
use App\Plugins\PluginManager;
use App\Plugins\RemoteSupport\Providers\RemoteProviders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Öffentliche Startseite ohne Anmeldung: Abschnitte, Sprunganker und
 * Kennzahlen. Die Kennzahlen halten die Seite beim Bestand — ein neues
 * Branchenprofil oder Modul ohne Eintrag auf der Startseite fällt hier auf.
 */
class GuestHomePageTest extends TestCase {
    use RefreshDatabase;

    public function test_guest_sees_all_sections_with_anchors(): void {
        $response = $this->get(route('home'))->assertOk();

        foreach (['funktionen', 'branchen', 'integrationen', 'plattform'] as $anchor) {
            $response->assertSee('id="' . $anchor . '"', false)
                ->assertSee('href="#' . $anchor . '"', false);
        }

        $response->assertSee(__('Vorkonfiguriert für Ihre Branche'))
            ->assertSee(__('Lernplattform'))
            ->assertSee(__('Vereinsverwaltung'))
            ->assertSee(__('Formate & Standards'))
            ->assertSee(__('Bereit, loszulegen?'));
    }

    public function test_module_stat_counts_core_and_feature_modules(): void {
        $expected = count(array_filter(
            app(ModuleRegistry::class)->all(),
            static fn(Manifest $manifest): bool => $manifest->kind() !== ModuleKind::Platform,
        ));

        $this->assertSame($expected, $this->stat(__('Module')));
    }

    public function test_every_branch_profile_is_listed(): void {
        $profiles = glob(database_path('data/branchprofiles/*.php')) ?: [];

        $this->assertSame(count($profiles), $this->stat(__('Branchenprofile')), 'Neues Branchenprofil auch in resources/views/home.blade.php ($branches) eintragen.');
    }

    /** gl-148: Die Anbindungen folgen den Plugins mit Implementierung, nicht einer Handliste. */
    public function test_every_plugin_product_is_listed_in_a_named_group(): void {
        $response = $this->get(route('home'))->assertOk();
        $html = (string) $response->getContent();
        $plugins = app(PluginManager::class)->all();
        $this->assertNotEmpty($plugins);

        foreach ($plugins as $id => $plugin) {
            foreach (HomeController::PRODUCT_NAMES[$id] ?? [$plugin->name()] as $name) {
                $this->assertStringContainsString(e($name), $html, "Plugin {$id} fehlt auf der Startseite.");
            }
        }
        foreach (RemoteProviders::labels() as $label) {
            $this->assertStringContainsString(e($label), $html, "Fernwartungs-Anbieter {$label} fehlt — HomeController::PRODUCT_NAMES['remote-support'] nachziehen.");
        }
        $this->assertStringNotContainsString(e(__('Weitere Anbindungen')), $html, 'Plugin ohne Startseiten-Gruppe — Modul in HomeController::integrationGroups() zuordnen.');

        $chips = array_sum(array_map(static fn(array $group): int => count($group['items']), (array) $response->viewData('integrations')));
        $this->assertSame($chips, $this->stat(__('Anbindungen')));
    }

    private function stat(string $label): ?int {
        $html = $this->get(route('home'))->assertOk()->getContent();
        $pattern = '~<dt[^>]*>\s*' . preg_quote(e($label), '~') . '\s*</dt>\s*<dd[^>]*>\s*(\d+)\s*</dd>~u';

        return preg_match($pattern, (string) $html, $m) === 1 ? (int) $m[1] : null;
    }
}
