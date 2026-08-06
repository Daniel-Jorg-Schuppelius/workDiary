<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginSettingsResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

use App\Models\PluginSetting;

/**
 * Gemeinsames Overlay der Plugin-Configs (Konsolidierung C10): lädt die
 * plugin_settings-Zeile der Organisation (Kontext-Fallback wie bisher) und
 * bietet typisierte Getter — Org-Setting vor `config('plugins.<id>.*')`.
 * Leere Strings zählen als „nicht gesetzt" (leere encrypted-Strings ⇒ null).
 * Die `resolve()`-Methoden der Configs behalten ihre typisierten
 * Rückgabe-Arrays; hier liegt nur Lookup + Cast.
 */
final class PluginSettingsResolver {
    /** @param array<string, mixed> $settings */
    private function __construct(
        private readonly string $configPrefix,
        private readonly ?PluginSetting $row,
        private readonly array $settings,
    ) {}

    /** Lädt die Overlay-Zeile; `$configKey` nur nötig, wenn Config-Prefix ≠ Plugin-ID. */
    public static function for(string $pluginId, ?int $organizationId = null, ?string $configKey = null): self {
        $organizationId ??= PluginOrgContext::currentId();
        $row = null;
        // IDs ≤ 0 sind keine reale Organisation (0 = Instanz-Sentinel bzw.
        // ungesetzte Stub-Verbindung) → reine Config-Auflösung, kein DB-Zugriff.
        if ($organizationId !== null && $organizationId > 0) {
            $row = PluginSetting::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('plugin_id', $pluginId)
                ->first();
        }
        $settings = $row?->settings;

        return new self('plugins.' . ($configKey ?? $pluginId), $row, is_array($settings) ? $settings : []);
    }

    public function hasRow(): bool {
        return $this->row !== null;
    }

    /** Der Org-Schalter hat Vorrang; ohne Zeile zählt die Config. */
    public function enabled(bool $default = false): bool {
        if ($this->row !== null) {
            return (bool) $this->row->enabled;
        }

        return (bool) config($this->configPrefix . '.enabled', $default);
    }

    /** Nicht-leerer String: Org-Setting vor Config, sonst `$default`. */
    public function string(string $key, ?string $default = null, bool $trim = false): ?string {
        return $this->stringValue($this->settings[$key] ?? null, $trim)
            ?? $this->stringValue(config($this->configPrefix . '.' . $key), $trim)
            ?? $default;
    }

    /** Nur aus den Org-Settings, nie Config — z. B. Webhook-Geheimnisse. */
    public function settingString(string $key, bool $trim = false): ?string {
        return $this->stringValue($this->settings[$key] ?? null, $trim);
    }

    public function int(string $key, int $default): int {
        return $this->intOrNull($key) ?? $default;
    }

    public function intOrNull(string $key): ?int {
        $value = $this->settings[$key] ?? null;
        if (is_numeric($value)) {
            return (int) $value;
        }
        $config = config($this->configPrefix . '.' . $key);

        return is_numeric($config) ? (int) $config : null;
    }

    public function bool(string $key, bool $default): bool {
        $value = $this->settings[$key] ?? null;
        if ($value !== null) {
            return filter_var($value, FILTER_VALIDATE_BOOL);
        }

        return (bool) config($this->configPrefix . '.' . $key, $default);
    }

    public function float(string $key, float $default): float {
        $value = $this->settings[$key] ?? null;
        if (is_numeric($value)) {
            return (float) $value;
        }
        $config = config($this->configPrefix . '.' . $key);

        return is_numeric($config) ? (float) $config : $default;
    }

    private function stringValue(mixed $value, bool $trim): ?string {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }
        $value = (string) $value;
        if ($trim) {
            $value = trim($value);
        }

        return $value !== '' ? $value : null;
    }
}
