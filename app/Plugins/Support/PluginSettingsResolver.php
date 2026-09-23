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

use App\Models\Platform\PluginSetting;
use App\Plugins\Contracts\Plugin;

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
        private readonly string $pluginId,
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

        return new self($pluginId, 'plugins.' . ($configKey ?? $pluginId), $row, is_array($settings) ? $settings : []);
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

    /**
     * Schlüsselnamen, die ein Zugangsgeheimnis bezeichnen.
     *
     * Für sie greift der Config-/ENV-Rückfall nur, wenn der Betreiber ihn
     * ausdrücklich zulässt ({@see secretFallbackAllowed()}).
     */
    private const SECRET_KEY_PATTERN = '/(^|_)(api_key|api_token|api_secret|api_password|token|secret|password|key|keystring)$/';

    /**
     * Geheimnisse, die zur App-Registrierung des BETREIBERS gehören und
     * deshalb weiter auf die Config/ENV zurückfallen dürfen.
     *
     * Der Unterschied zu einem Zugangsgeheimnis ist der Datenzugriff: Ein
     * `api_key` öffnet unmittelbar das Konto des Betreibers — fällt eine
     * Organisation darauf zurück, sieht sie fremde Daten. Eine
     * App-Registrierung (`client_secret` eines OAuth-Flusses) öffnet gar
     * nichts: Die Organisation meldet sich weiter mit ihrem eigenen Konto an,
     * ihr Token liegt in ihrer eigenen Verbindungszeile. Die
     * App-Registrierung ist nur der Briefkopf, unter dem die Anmeldung läuft.
     * Ohne diese Ausnahme fiele in jeder Installation, die ihre Instanz-App
     * über die .env pflegt, die Anmeldung für alle Organisationen aus.
     *
     * Wer eine EIGENE App-Registrierung hinterlegt, überschreibt sie ohnehin
     * je Organisation — die Liste betrifft nur den Rückfall.
     *
     * @var array<string, list<string>>
     */
    private const INSTANCE_SECRET_KEYS = [
        'msgraph' => ['client_secret'],
        'sharepoint' => ['client_secret'],
        'google_calendar' => ['client_secret'],
        'todoist' => ['client_secret'],
        'calendly' => ['client_secret'],
    ];

    /** Nicht-leerer String: Org-Setting vor Config, sonst `$default`. */
    public function string(string $key, ?string $default = null, bool $trim = false): ?string {
        $own = $this->stringValue($this->settings[$key] ?? null, $trim);
        if ($own !== null) {
            return $own;
        }

        // Geheimnisse fallen nur mit ausdrücklicher Erlaubnis auf die Config
        // zurück (Sicherheitsscan 2026-08-23, S-28). Im Einzelplatzbetrieb ist
        // der Rückfall die gewollte Bequemlichkeit — dort steht in der .env der
        // eigene Schlüssel. Im Mehrmandantenbetrieb arbeitete ein Mandant ohne
        // eigene Zugangsdaten damit still über den Schlüssel des Betreibers
        // und sah fremde Daten.
        if (self::looksLikeSecretKey($key) && ! self::isInstanceSecretKey($this->pluginId, $key) && ! self::secretFallbackAllowed()) {
            return $default;
        }

        return $this->stringValue(config($this->configPrefix . '.' . $key), $trim) ?? $default;
    }

    /** Darf ein Zugangsgeheimnis auf Config/ENV zurückfallen? Vorgabe seit dem Audit 2026-09-13: nein. */
    public static function secretFallbackAllowed(): bool {
        return (bool) config('plugins.allow_env_secret_fallback', true);
    }

    /** Gehört der Schlüssel zur App-Registrierung des Betreibers? */
    public static function isInstanceSecretKey(string $pluginId, string $key): bool {
        return in_array($key, self::INSTANCE_SECRET_KEYS[$pluginId] ?? [], true);
    }

    /** Bezeichnet der Schlüsselname ein Zugangsgeheimnis? */
    public static function looksLikeSecretKey(string $key): bool {
        return preg_match(self::SECRET_KEY_PATTERN, $key) === 1;
    }

    /**
     * Zugangsgeheimnisse eines Plugins, die in der Betreiber-Konfiguration
     * stehen und keine App-Registrierung sind — genau die Werte, die der
     * Rückfall-Schalter betrifft.
     *
     * @return list<string>
     */
    public static function operatorSecretKeys(Plugin $plugin): array {
        $keys = [];
        foreach ($plugin->settingsSchema() as $field) {
            $key = (string) $field['key'];
            if (! self::looksLikeSecretKey($key) || self::isInstanceSecretKey($plugin->id(), $key)) {
                continue;
            }
            $configured = config('plugins.' . $plugin->id() . '.' . $key);
            if (! is_string($configured) || trim($configured) === '') {
                continue;
            }
            $keys[] = $key;
        }

        return $keys;
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
