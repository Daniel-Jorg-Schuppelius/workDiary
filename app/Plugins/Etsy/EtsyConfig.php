<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EtsyConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Etsy;

use App\Plugins\Support\PluginSettingsResolver;

/**
 * Effektive Etsy-Konfiguration je Organisation (Feature 101, Muster
 * BillbeeConfig): App-Credentials (Keystring/Shared Secret der ORG-EIGENEN
 * Seller-App) aus plugin_settings vor config('plugins.etsy.*'); das
 * Webhook-Secret kommt AUSSCHLIESSLICH aus den Org-Settings
 * (`settingString()`, nie Config/ENV — es gehört zur Portal-Registrierung
 * der Org). Endpoint-URLs sind config-only (Allowlist-Regel).
 */
class EtsyConfig {
    /**
     * @return array{keystring: ?string, shared_secret: ?string, webhook_secret: ?string, base_url: string, authorize_url: string, token_url: string, scopes: string, import_from: ?string, sync_page_budget: int, enabled: bool}
     */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(EtsyPlugin::ID, $organizationId);

        return [
            'keystring' => $r->string('keystring'),
            'shared_secret' => $r->string('shared_secret'),
            'webhook_secret' => $r->settingString('webhook_secret'),
            'base_url' => rtrim((string) config('plugins.etsy.base_url'), '/'),
            'authorize_url' => (string) config('plugins.etsy.authorize_url'),
            'token_url' => (string) config('plugins.etsy.token_url'),
            'scopes' => (string) config('plugins.etsy.scopes'),
            'import_from' => $r->string('import_from'),
            'sync_page_budget' => max(1, $r->int('sync_page_budget', (int) config('plugins.etsy.sync_page_budget', 10))),
            'enabled' => $r->enabled(),
        ];
    }

    /**
     * Verbindbar, sobald die Org-eigene Seller-App komplett hinterlegt ist —
     * beide Teile sind nötig: der Keystring als OAuth-client_id UND das
     * Shared Secret für den `x-api-key: keystring:shared_secret`-Header
     * (W0-Preflight 2026-08-04).
     */
    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return ($config['keystring'] ?? '') !== '' && ($config['shared_secret'] ?? '') !== '';
    }
}
