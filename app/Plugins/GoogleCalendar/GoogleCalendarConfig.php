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

/**
 * Installationsweite Google-Kalender-Konfiguration (MVP-328, Bauturbo A8):
 * Client-ID/-Secret kommen ausschließlich aus ENV/config — NIE je
 * Organisation (die per-Org-Daten liegen in `google_calendar_connections`).
 */
class GoogleCalendarConfig {
    /** @return array{client_id: string, client_secret: string, api_base: string, authorize_url: string, token_url: string, scopes: string} */
    public static function resolve(): array {
        return [
            'client_id' => (string) config('plugins.google_calendar.client_id', ''),
            'client_secret' => (string) config('plugins.google_calendar.client_secret', ''),
            'api_base' => rtrim((string) config('plugins.google_calendar.api_base', 'https://www.googleapis.com/calendar/v3'), '/'),
            'authorize_url' => (string) config('plugins.google_calendar.authorize_url', 'https://accounts.google.com/o/oauth2/v2/auth'),
            'token_url' => (string) config('plugins.google_calendar.token_url', 'https://oauth2.googleapis.com/token'),
            'scopes' => (string) config('plugins.google_calendar.scopes', 'https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.calendarlist.readonly'),
        ];
    }

    public static function isConfigured(): bool {
        $config = self::resolve();

        return $config['client_id'] !== '' && $config['client_secret'] !== '';
    }
}
