<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SelfHostedSettings.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

/**
 * Einstellungsschema selbst gehosteter Quellen (Phase 158; Konsolidierung
 * kon-05): Beschriftung und Hilfe jeder Zeile kommen aus
 * `<id>::<id>.settings.<key>` und `<key>_help`; das Standardschema ist
 * Basis-URL, Zugang, Opt-in für private Adressen.
 *
 * @phpstan-type SettingsRow array{key: string, label: string, type: string, help?: string, required?: bool, default?: mixed}
 */
final class SelfHostedSettings {
    /**
     * Basis-URL, dann `$fields` (Zugang), dann `allow_private_network`, dann `$trailing`.
     *
     * @param  list<SettingsRow>  $fields
     * @param  list<SettingsRow>  $trailing
     * @return list<SettingsRow>
     */
    public static function schema(string $pluginId, array $fields, array $trailing = []): array {
        return [
            self::field($pluginId, 'base_url'),
            ...$fields,
            self::field($pluginId, 'allow_private_network', 'boolean', required: false, default: false),
            ...$trailing,
        ];
    }

    /**
     * Eine Zeile des Schemas; `required`, `default` und `help` erscheinen nur, wenn gesetzt
     * ({@see \App\Plugins\Contracts\SettingsField::fromArray()} liest fehlende Schlüssel als aus).
     *
     * @return SettingsRow
     */
    public static function field(string $pluginId, string $key, string $type = 'text', bool $required = true, mixed $default = null, bool $help = true): array {
        $labels = $pluginId . '::' . $pluginId . '.settings.';
        $row = ['key' => $key, 'label' => (string) __($labels . $key), 'type' => $type];
        if ($required) {
            $row['required'] = true;
        }
        if ($default !== null) {
            $row['default'] = $default;
        }
        if ($help) {
            $row['help'] = (string) __($labels . $key . '_help');
        }

        return $row;
    }
}
