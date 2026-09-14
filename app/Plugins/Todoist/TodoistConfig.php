<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodoistConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Todoist;

use App\Plugins\Support\PluginSettingsResolver;

/**
 * Todoist-Konfiguration (Feature 055, MVP-111): `client_id`/`client_secret`
 * kommen aus dem Plugin-Settings-Overlay der Organisation (verschlüsselt in
 * `plugin_settings`, Dialog auf der Plugin-Seite) mit Rückfall auf die
 * installationsweite ENV — jede Organisation kann eine EIGENE Todoist-App
 * hinterlegen, ohne Overlay gilt die Instanz-App. Die Konten selbst liegen
 * weiterhin in `todoist_connections`. `$organizationId`: null =
 * Request-Org-Kontext, {@see self::INSTANCE} = ausdrücklich nur die
 * Instanz-App (so prüft der Webhook-Endpunkt, der die Organisation noch nicht
 * kennt).
 *
 * Sicherheitsleitplanke: Endpunkte (`authorize_url`/`token_url`/`api_base`)
 * und die Scopes bleiben BEWUSST config-only — sonst könnte ein Org-Admin den
 * Token-Fluss umlenken oder Scopes eskalieren.
 */
class TodoistConfig {
    /** Sentinel: Instanz-App aus der ENV erzwingen (kein Org-Overlay). */
    public const INSTANCE = 0;

    /** @return array{client_id: string, client_secret: string, api_base: string, authorize_url: string, token_url: string, scopes: string} */
    public static function resolve(?int $organizationId = null): array {
        $overlay = $organizationId === self::INSTANCE
            ? null
            : PluginSettingsResolver::for(TodoistPlugin::ID, $organizationId);

        $string = static fn (string $key): string => $overlay !== null
            ? (string) $overlay->string($key, '', trim: true)
            : trim((string) (config('plugins.todoist.' . $key) ?? ''));

        return [
            'client_id' => $string('client_id'),
            'client_secret' => $string('client_secret'),
            'api_base' => rtrim((string) config('plugins.todoist.api_base', 'https://api.todoist.com/api/v1'), '/'),
            'authorize_url' => (string) config('plugins.todoist.authorize_url', 'https://todoist.com/oauth/authorize'),
            'token_url' => (string) config('plugins.todoist.token_url', 'https://todoist.com/oauth/access_token'),
            'scopes' => (string) config('plugins.todoist.scopes', 'data:read_write'),
        ];
    }

    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return $config['client_id'] !== '' && $config['client_secret'] !== '';
    }
}
