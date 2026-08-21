<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginCapabilityOverviewTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Plugins\Contracts\PluginCapability;
use App\Plugins\PluginManager;
use App\Plugins\SevDesk\SevDeskPlugin;
use App\Plugins\Support\PluginCapabilityOverview;
use App\Plugins\Toggl\TogglPlugin;
use Tests\TestCase;

/**
 * Anzeige- vs. Vertrags-Fähigkeiten (Entscheid 2026-08-21 zum Audit-Befund
 * W1.6): Die Übersicht zeigt zusätzlich, was über eigene Registries läuft —
 * ohne dass die Plugin-Klasse dafür ein Interface implementieren muss.
 */
final class PluginCapabilityOverviewTest extends TestCase {
    private function overview(): PluginCapabilityOverview {
        return app(PluginCapabilityOverview::class);
    }

    public function test_facturation_target_is_shown_although_the_plugin_declares_nothing(): void {
        $plugin = app(PluginManager::class)->get(SevDeskPlugin::ID);
        $this->assertNotNull($plugin);

        // Vertrag unverändert: sevDesk erklärt bewusst keine Capability.
        $this->assertSame([], $plugin->capabilities());

        $labels = $this->overview()->labelsFor($plugin);

        $this->assertContains((string) __('plugins.capability.facturation'), $labels);
    }

    public function test_declared_capabilities_stay_first(): void {
        $plugin = app(PluginManager::class)->get(TogglPlugin::ID);
        $this->assertNotNull($plugin);

        $labels = $this->overview()->labelsFor($plugin);

        $this->assertNotSame([], $plugin->capabilities());
        $this->assertSame((string) PluginCapability::TimeImport->label(), $labels[0] ?? null);
    }

    public function test_a_plugin_without_registry_entry_gets_no_extra_label(): void {
        $this->assertSame([], $this->overview()->registryLabels('gibt-es-nicht'));
    }

    public function test_file_target_is_not_a_plugin_and_stays_out(): void {
        // `file` ist ein TransferTarget, aber kein Plugin — die Übersicht darf
        // daraus keine Plugin-Fähigkeit ableiten.
        $this->assertNull(app(PluginManager::class)->get('file'));
    }
}
