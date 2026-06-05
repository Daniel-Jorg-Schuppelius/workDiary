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

use App\Plugins\Contracts\{Plugin, PluginCapability};
use Tests\TestCase;

/**
 * Architektur-Gate für das Plugin-System: erzwingt die Vertrags-Invarianten
 * für jedes in config/plugins.php registrierte Plugin (vgl. `plugin:doctor`).
 */
class PluginContractTest extends TestCase {
    /** @return array<int, class-string> */
    private function pluginClasses(): array {
        return (array) config('plugins.classes', []);
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
                $this->assertInstanceOf(PluginCapability::class, $cap, "$class: capabilities() muss Enum-Cases liefern.");
                $interface = $cap->interface();
                $this->assertInstanceOf($interface, $plugin, "$class kündigt {$cap->name} an, implementiert aber $interface nicht.");
            }
        }
    }

    public function test_settings_schema_is_well_formed(): void {
        $allowed = ['text', 'password', 'select', 'boolean'];
        foreach ($this->pluginClasses() as $class) {
            foreach ($this->app->make($class)->settingsSchema() as $field) {
                $this->assertArrayHasKey('key', $field, "$class: Feld ohne key.");
                $this->assertArrayHasKey('label', $field, "$class: Feld ohne label.");
                $this->assertArrayHasKey('type', $field, "$class: Feld ohne type.");
                $this->assertContains($field['type'], $allowed, "$class: unbekannter Feldtyp {$field['type']}.");
                if ($field['type'] === 'select') {
                    $this->assertNotEmpty($field['options'] ?? [], "$class: select-Feld {$field['key']} ohne options.");
                }
            }
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
