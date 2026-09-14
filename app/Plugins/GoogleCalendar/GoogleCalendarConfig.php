<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleCalendarConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\GoogleCalendar;

use App\Plugins\Support\PluginSettingsResolver;

/**
 * Google-Kalender-Konfiguration (MVP-328, Bauturbo A8): `client_id`/
 * `client_secret` kommen aus dem Plugin-Settings-Overlay der Organisation
 * (verschlüsselt in `plugin_settings`, Dialog auf der Plugin-Seite) mit
 * Rückfall auf die installationsweite ENV — jede Organisation kann ein
 * EIGENES Google-Cloud-Projekt hinterlegen, ohne Overlay gilt die Instanz-App.
 * Die Konten selbst liegen weiterhin in `google_calendar_connections`.
 * `$organizationId`: null = Request-Org-Kontext, {@see self::INSTANCE} =
 * ausdrücklich nur die Instanz-App.
 *
 * Sicherheitsleitplanke: Endpunkte (`authorize_url`/`token_url`/`api_base`)
 * und die Scopes bleiben BEWUSST config-only — sonst könnte ein Org-Admin den
 * Token-Fluss umlenken oder Scopes eskalieren.
 */
class GoogleCalendarConfig {
    /** Sentinel: Instanz-App aus der ENV erzwingen (kein Org-Overlay). */
    public const INSTANCE = 0;

    /** @return array{client_id: string, client_secret: string, api_base: string, authorize_url: string, token_url: string, scopes: string} */
    public static function resolve(?int $organizationId = null): array {
        $overlay = $organizationId === self::INSTANCE
            ? null
            : PluginSettingsResolver::for(GoogleCalendarPlugin::ID, $organizationId);

        $string = static fn (string $key): string => $overlay !== null
            ? (string) $overlay->string($key, '', trim: true)
            : trim((string) (config('plugins.google_calendar.' . $key) ?? ''));

        return [
            'client_id' => $string('client_id'),
            'client_secret' => $string('client_secret'),
            'api_base' => rtrim((string) config('plugins.google_calendar.api_base', 'https://www.googleapis.com/calendar/v3'), '/'),
            'authorize_url' => (string) config('plugins.google_calendar.authorize_url', 'https://accounts.google.com/o/oauth2/v2/auth'),
            'token_url' => (string) config('plugins.google_calendar.token_url', 'https://oauth2.googleapis.com/token'),
            'scopes' => (string) config('plugins.google_calendar.scopes', 'https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.calendarlist.readonly'),
        ];
    }

    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return $config['client_id'] !== '' && $config['client_secret'] !== '';
    }
}
