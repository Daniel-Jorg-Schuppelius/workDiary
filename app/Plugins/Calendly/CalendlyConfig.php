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

/**
 * Installationsweite Calendly-Konfiguration (Feature 095): Client-ID/-Secret
 * und OAuth-/API-Endpunkte kommen ausschließlich aus ENV/config — NIE je
 * Organisation (die per-Org-Tokens liegen in `calendly_connections`). Ob das
 * Plugin je Organisation aktiv ist, entscheidet {@see CalendlyPlugin::isEnabled()}
 * über `plugin_settings`.
 */
class CalendlyConfig {
    public const DEFAULT_API_BASE = 'https://api.calendly.com';

    /** @return array{client_id: string, client_secret: string, api_base: string, authorize_url: string, token_url: string, scopes: string, backfill_days_past: int, backfill_days_future: int} */
    public static function resolve(): array {
        return [
            'client_id' => (string) config('plugins.calendly.client_id', ''),
            'client_secret' => (string) config('plugins.calendly.client_secret', ''),
            'api_base' => rtrim((string) config('plugins.calendly.api_base', self::DEFAULT_API_BASE), '/'),
            'authorize_url' => (string) config('plugins.calendly.authorize_url', 'https://auth.calendly.com/oauth/authorize'),
            'token_url' => (string) config('plugins.calendly.token_url', 'https://auth.calendly.com/oauth/token'),
            'scopes' => (string) config('plugins.calendly.scopes', ''),
            'backfill_days_past' => max(1, (int) config('plugins.calendly.backfill_days_past', 7)),
            'backfill_days_future' => max(1, (int) config('plugins.calendly.backfill_days_future', 60)),
        ];
    }

    public static function isConfigured(): bool {
        $config = self::resolve();

        return $config['client_id'] !== '' && $config['client_secret'] !== '';
    }
}
