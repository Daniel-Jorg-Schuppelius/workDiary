<?php
/*
 * Created on   : Fri Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginContractTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use App\Plugins\Contracts\{Plugin, PluginCapabilityContract, SettingsField};
use App\Plugins\PluginDiscovery;
use Tests\TestCase;

/**
 * Architektur-Gate für das Plugin-System: erzwingt die Vertrags-Invarianten
 * für jedes geladene Plugin (Auto-Discovery + explizite Config; vgl. `plugin:doctor`).
 */
class PluginContractTest extends TestCase {
    /** @return array<int, class-string> */
    private function pluginClasses(): array {
        return PluginDiscovery::classes();
    }

    public function test_registered_classes_implement_plugin_contract(): void {
        foreach ($this->pluginClasses() as $class) {
            $this->assertTrue(is_subclass_of($class, Plugin::class), "$class implementiert App\\Plugins\\Contracts\\Plugin nicht.");
        }
    }

    public function test_plugin_ids_are_well_formed_and_unique(): void {
        $ids = [];
        foreach ($this->pluginClasses() as $class) {
            $id = $this->app->make($class)->id();
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_-]*$/', $id, "Ungültige Plugin-ID: $id");
            $this->assertNotContains($id, $ids, "Doppelte Plugin-ID: $id");
            $ids[] = $id;
        }
    }

    public function test_advertised_capabilities_have_matching_interface(): void {
        foreach ($this->pluginClasses() as $class) {
            $plugin = $this->app->make($class);
            foreach ($plugin->capabilities() as $cap) {
                // W5e: Kern-Enum ODER Registry-Capability — beide erfüllen den Contract.
                $this->assertInstanceOf(PluginCapabilityContract::class, $cap, "$class: capabilities() muss PluginCapabilityContract-Instanzen liefern.");
                $interface = $cap->interface();
                $this->assertInstanceOf($interface, $plugin, "$class kündigt {$cap->identifier()} an, implementiert aber $interface nicht.");
            }
        }
    }

    public function test_settings_schema_is_well_formed(): void {
        foreach ($this->pluginClasses() as $class) {
            foreach ($this->app->make($class)->settingsSchema() as $field) {
                // W5b: Normalisierung wirft bei ungültigem Typ/Key/select ohne
                // options — dieselbe Prüfung wie `plugin:doctor` und Controller.
                try {
                    SettingsField::fromArray($field);
                } catch (\InvalidArgumentException $e) {
                    $this->fail("$class: " . $e->getMessage());
                }
            }
            $this->addToAssertionCount(1);
        }
    }

    public function test_migrations_path_and_service_provider_resolve(): void {
        foreach ($this->pluginClasses() as $class) {
            $plugin = $this->app->make($class);

            $path = $plugin->migrationsPath();
            if ($path !== null) {
                $this->assertDirectoryExists($path, "$class: migrationsPath existiert nicht.");
            }

            $provider = $plugin->serviceProvider();
            if ($provider !== null) {
                $this->assertTrue(class_exists($provider), "$class: serviceProvider-Klasse fehlt: $provider");
            }
        }
    }
}
