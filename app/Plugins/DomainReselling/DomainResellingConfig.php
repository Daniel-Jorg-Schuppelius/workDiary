<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainResellingConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\DomainReselling;

use App\Enums\Domain\DomainProviderEnvironment;
use App\Plugins\Support\PluginSettingsResolver;

/**
 * Liefert die effektive DomainReselling-Konfiguration: plugin_settings der
 * Organisation vor config('plugins.domainreselling.*') — Lookup/Cast im
 * {@see PluginSettingsResolver} (C10). Der Endpunkt kommt ausschließlich aus
 * der festen Allowlist je Umgebung und ist NIE org-überschreibbar.
 */
class DomainResellingConfig {
    /**
     * @return array{enabled: bool, timeout: int, endpoints: array<string, string>, call_path: string, check_budget_per_hour: int, check_cache_ttl: int, list_page_size: int, stale_after_hours: int}
     */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(DomainResellingPlugin::ID, $organizationId);

        return [
            'enabled' => $r->enabled(),
            'timeout' => max(1, $r->int('timeout', 20)),
            // Sicherheits-Allowlist: NIE aus plugin_settings, nur config().
            'endpoints' => self::endpoints(),
            'call_path' => self::callPath(),
            'check_budget_per_hour' => max(1, $r->int('check_budget_per_hour', 300)),
            'check_cache_ttl' => max(0, $r->int('check_cache_ttl', 300)),
            // 0/negativ würde die Paginierungs-Schleife des Syncs brechen.
            'list_page_size' => max(1, $r->int('list_page_size', 100)),
            'stale_after_hours' => max(1, $r->int('stale_after_hours', 24)),
        ];
    }

    /** Allowlistete Basis-URL für die Umgebung. */
    public static function endpointUrl(DomainProviderEnvironment $environment): string {
        $endpoints = self::endpoints();

        return rtrim($endpoints[$environment->value] ?? $endpoints['production'], '/');
    }

    /** Vollständige call.cgi-URL für die Umgebung. */
    public static function callUrl(DomainProviderEnvironment $environment): string {
        return self::endpointUrl($environment) . self::callPath();
    }

    /**
     * Feste Endpoint-Allowlist — bewusst nur config(), nie plugin_settings,
     * damit Zugangsdaten nie an einen fremden Host gesendet werden.
     *
     * @return array<string, string>
     */
    private static function endpoints(): array {
        $config = config('plugins.domainreselling.endpoints');
        /** @var array<string, string> $endpoints */
        $endpoints = is_array($config) ? $config : [];

        return $endpoints + [
            'ote' => 'https://api-ote.domainreselling.de',
            'production' => 'https://api.domainreselling.de',
        ];
    }

    /** Pfad zu call.cgi — wie die Endpoints nur config(), nie plugin_settings. */
    private static function callPath(): string {
        return (string) config('plugins.domainreselling.call_path', '/api/call.cgi');
    }
}
