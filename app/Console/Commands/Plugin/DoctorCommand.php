<?php
/*
 * Created on   : Fri Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DoctorCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\Plugin;

use App\Plugins\Contracts\Plugin;
use App\Plugins\PluginManager;
use Illuminate\Console\Command;

/**
 * Validiert die Plugin-Registry gegen die Contract-Invarianten — als CI-Gate
 * und Entwicklerhilfe. Spiegelt {@see \Tests\Unit\Architecture\PluginContractTest},
 * läuft aber gegen die zur Laufzeit aufgelösten Plugin-Instanzen.
 */
class DoctorCommand extends Command {
    protected $signature = 'plugin:doctor';

    protected $description = 'Prüft alle registrierten Plugins auf Vertrags-Konformität (IDs, Capability↔Interface, Settings-Schema, Migrations, Provider).';

    public function handle(PluginManager $manager): int {
        $violations = [];
        $warnings = [];
        $seen = [];

        // Ohne gesetzte APP_VERSION rechnet PluginCompatibility mit dem
        // Default `0.1.0-dev` — Plugins mit minAppVersion wären nie aktivierbar.
        if (config('app.version') === null || str_ends_with((string) config('app.version'), '-dev')) {
            $warnings[] = 'APP_VERSION ist ungesetzt/Dev-Default — Kompatibilitätsprüfungen laufen gegen ' . (string) config('app.version', '0.1.0-dev') . '.';
        }

        foreach ($manager->all() as $plugin) {
            $id = $plugin->id();

            if (! preg_match('/^[a-z][a-z0-9_-]*$/', $id)) {
                $violations[] = "{$id}: ungültiges ID-Format (erwartet ^[a-z][a-z0-9_-]*$).";
            }
            if (isset($seen[$id])) {
                $violations[] = "{$id}: doppelte Plugin-ID.";
            }
            $seen[$id] = true;

            $this->checkCapabilities($plugin, $violations);
            $this->checkSettingsSchema($plugin, $violations, $warnings);
            $this->checkSchema($plugin, $violations);
            $this->checkServiceProvider($plugin, $violations);
        }

        foreach ($warnings as $w) {
            $this->warn('  ⚠ ' . $w);
        }

        if ($violations === []) {
            $this->info(sprintf('Plugin-Registry ok — %d Plugin(s) geprüft, keine Verstöße.', $manager->all()->count()));

            return self::SUCCESS;
        }

        $this->error(sprintf('%d Verstoß/Verstöße gefunden:', count($violations)));
        foreach ($violations as $v) {
            $this->line('  • ' . $v);
        }

        return self::FAILURE;
    }

    /** @param list<string> $violations */
    private function checkCapabilities(Plugin $plugin, array &$violations): void {
        foreach ($plugin->capabilities() as $cap) {
            $interface = $cap->interface();
            if (! $plugin instanceof $interface) {
                $violations[] = sprintf('%s: kündigt %s an, implementiert aber %s nicht.', $plugin->id(), $cap->identifier(), $interface);
            }
        }
    }

    /**
     * @param  list<string>  $violations
     * @param  list<string>  $warnings
     */
    private function checkSettingsSchema(Plugin $plugin, array &$violations, array &$warnings): void {
        foreach ($plugin->settingsSchema() as $i => $field) {
            $where = "{$plugin->id()} settingsSchema[#{$i}]";
            // Normalisierung über das typisierte Feld (W5b): ungültige Typen,
            // Keys oder select ohne options werden dort zur Ausnahme.
            try {
                $normalized = \App\Plugins\Contracts\SettingsField::fromArray($field);
            } catch (\InvalidArgumentException $e) {
                $violations[] = "{$where}: {$e->getMessage()} (erlaubt: " . implode(', ', \App\Plugins\Contracts\FieldType::values()) . ').';

                continue;
            }
            // Heuristik (W1d, B6): key/token/secret-artige Felder ohne
            // Secret-Kennzeichnung landen im Klartext im HTML.
            if (! $normalized->isSecret() && preg_match('/(secret|token|password|api_?key)$/i', $normalized->key)) {
                $warnings[] = "{$where}: Feld \"{$normalized->key}\" wirkt geheim, ist aber nicht als secret/password gekennzeichnet.";
            }
        }
    }

    /** @param list<string> $violations */
    private function checkSchema(Plugin $plugin, array &$violations): void {
        $path = $plugin->migrationsPath();
        if ($path !== null && ! is_dir($path)) {
            $violations[] = "{$plugin->id()}: migrationsPath existiert nicht: {$path}.";
        }
    }

    /** @param list<string> $violations */
    private function checkServiceProvider(Plugin $plugin, array &$violations): void {
        $provider = $plugin->serviceProvider();
        if ($provider !== null && ! class_exists($provider)) {
            $violations[] = "{$plugin->id()}: serviceProvider-Klasse nicht gefunden: {$provider}.";
        }
    }
}
