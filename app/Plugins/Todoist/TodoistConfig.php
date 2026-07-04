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

/**
 * Installationsweite Todoist-Konfiguration (Feature 055, MVP-111):
 * Client-ID/-Secret kommen ausschließlich aus ENV/config — NIE je
 * Organisation (die per-Org-Daten liegen in `todoist_connections`).
 */
class TodoistConfig {
    /** @return array{client_id: string, client_secret: string, api_base: string, authorize_url: string, token_url: string, scopes: string} */
    public static function resolve(): array {
        return [
            'client_id' => (string) config('plugins.todoist.client_id', ''),
            'client_secret' => (string) config('plugins.todoist.client_secret', ''),
            'api_base' => rtrim((string) config('plugins.todoist.api_base', 'https://api.todoist.com/api/v1'), '/'),
            'authorize_url' => (string) config('plugins.todoist.authorize_url', 'https://todoist.com/oauth/authorize'),
            'token_url' => (string) config('plugins.todoist.token_url', 'https://todoist.com/oauth/access_token'),
            'scopes' => (string) config('plugins.todoist.scopes', 'data:read_write'),
        ];
    }

    public static function isConfigured(): bool {
        $config = self::resolve();

        return $config['client_id'] !== '' && $config['client_secret'] !== '';
    }
}
