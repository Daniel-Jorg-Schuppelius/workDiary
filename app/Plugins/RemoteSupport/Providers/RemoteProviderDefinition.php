<?php
/*
 * Created on   : Wed Nov 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteProviderDefinition.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\RemoteSupport\Providers;

use Closure;

/**
 * Beschreibt einen Fernwartungs-Anbieter für das Register ({@see RemoteProviders}):
 * Kennung, Bezeichnung, Referenztyp der Geräte-ID, Einstellungszeilen und die
 * Fabrik, die aus der aufgelösten Konfiguration den Client baut. Alles, was
 * je Anbieter verschieden ist, steht hier; Importer, Inbox, Panel und
 * Controller lesen nur das Register.
 *
 * @phpstan-type ConfigField array{key: string, cast: 'string'|'bool'|'int', default: mixed}
 * @phpstan-type SettingsRow array{key: string, label: string, type: string, help?: string, default?: mixed, options?: array<string, string>}
 */
final class RemoteProviderDefinition {
    /**
     * @param  list<ConfigField>  $configFields  Schlüssel ohne Anbieterpräfix; Org-Einstellung `<id>_<key>` vor `plugins.remote-support.<id>.<key>`
     * @param  Closure(): list<SettingsRow>  $settings  Einstellungszeilen ohne den Schalter `<id>_enabled`, Schlüssel bereits mit Präfix
     * @param  Closure(array<string, mixed>, ?int): ?RemoteProvider  $factory  Client aus der aufgelösten Anbieter-Config (null = unvollständig)
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $placeholder,
        public readonly array $configFields,
        private readonly Closure $settings,
        private readonly Closure $factory,
    ) {}

    /** external_type der Geräte-Referenz in `external_references`. */
    public function deviceType(): string {
        return $this->id . '_id';
    }

    /**
     * Schalter plus die Zeilen des Anbieters — in der Reihenfolge des Admin-Formulars.
     *
     * @return list<SettingsRow>
     */
    public function settingsSchema(): array {
        return [
            ['key' => $this->id . '_enabled', 'label' => (string) __(':provider aktiv', ['provider' => $this->label]), 'type' => 'boolean', 'default' => false],
            ...($this->settings)(),
        ];
    }

    /**
     * @param  array<string, mixed>  $config  Anbieter-Teil aus {@see \App\Plugins\RemoteSupport\RemoteSupportConfig::resolve()}
     */
    public function make(array $config, ?int $organizationId = null): ?RemoteProvider {
        return ($this->factory)($config, $organizationId);
    }
}
