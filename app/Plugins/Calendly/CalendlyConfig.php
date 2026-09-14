<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendlyConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Calendly;

use App\Plugins\Support\PluginSettingsResolver;

/**
 * Calendly-Konfiguration (Feature 095): `client_id`/`client_secret` kommen aus
 * dem Plugin-Settings-Overlay der Organisation (verschlüsselt in
 * `plugin_settings`, Dialog auf der Plugin-Seite) mit Rückfall auf die
 * installationsweite ENV — jede Organisation kann eine EIGENE Calendly-App
 * hinterlegen, ohne Overlay gilt die Instanz-App. Die Tokens selbst liegen
 * weiterhin in `calendly_connections`. Ob das Plugin je Organisation aktiv
 * ist, entscheidet {@see CalendlyPlugin::isEnabled()}. `$organizationId`:
 * null = Request-Org-Kontext, {@see self::INSTANCE} = ausdrücklich nur die
 * Instanz-App.
 *
 * Sicherheitsleitplanke: Endpunkte (`authorize_url`/`token_url`/`api_base`)
 * und die Scopes bleiben BEWUSST config-only — sonst könnte ein Org-Admin den
 * Token-Fluss umlenken oder Scopes eskalieren.
 */
class CalendlyConfig {
    public const DEFAULT_API_BASE = 'https://api.calendly.com';

    /** Sentinel: Instanz-App aus der ENV erzwingen (kein Org-Overlay). */
    public const INSTANCE = 0;

    /** @return array{client_id: string, client_secret: string, api_base: string, authorize_url: string, token_url: string, scopes: string, backfill_days_past: int, backfill_days_future: int} */
    public static function resolve(?int $organizationId = null): array {
        $overlay = $organizationId === self::INSTANCE
            ? null
            : PluginSettingsResolver::for(CalendlyPlugin::ID, $organizationId);

        $string = static fn (string $key): string => $overlay !== null
            ? (string) $overlay->string($key, '', trim: true)
            : trim((string) (config('plugins.calendly.' . $key) ?? ''));

        return [
            'client_id' => $string('client_id'),
            'client_secret' => $string('client_secret'),
            'api_base' => rtrim((string) config('plugins.calendly.api_base', self::DEFAULT_API_BASE), '/'),
            'authorize_url' => (string) config('plugins.calendly.authorize_url', 'https://auth.calendly.com/oauth/authorize'),
            'token_url' => (string) config('plugins.calendly.token_url', 'https://auth.calendly.com/oauth/token'),
            'scopes' => (string) config('plugins.calendly.scopes', ''),
            'backfill_days_past' => max(1, (int) config('plugins.calendly.backfill_days_past', 7)),
            'backfill_days_future' => max(1, (int) config('plugins.calendly.backfill_days_future', 60)),
        ];
    }

    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return $config['client_id'] !== '' && $config['client_secret'] !== '';
    }
}
