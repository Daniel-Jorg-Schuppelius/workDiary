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

/**
 * Aufgelöste Installations-Konfiguration des DomainReselling-Plugins
 * (Muster {@see \App\Plugins\Nextcloud\NextcloudConfig}). Der Endpunkt kommt
 * ausschließlich aus der festen Allowlist je Umgebung.
 */
class DomainResellingConfig {
    /**
     * @return array{enabled: bool, timeout: int, endpoints: array<string, string>, call_path: string, check_budget_per_hour: int, check_cache_ttl: int, list_page_size: int, stale_after_hours: int}
     */
    public static function resolve(): array {
        /** @var array<string, mixed> $config */
        $config = (array) config('plugins.domainreselling', []);

        /** @var array<string, string> $endpoints */
        $endpoints = is_array($config['endpoints'] ?? null) ? $config['endpoints'] : [];

        return [
            'enabled' => (bool) ($config['enabled'] ?? false),
            'timeout' => (int) ($config['timeout'] ?? 20),
            'endpoints' => $endpoints + [
                'ote' => 'https://api-ote.domainreselling.de',
                'production' => 'https://api.domainreselling.de',
            ],
            'call_path' => (string) ($config['call_path'] ?? '/api/call.cgi'),
            'check_budget_per_hour' => (int) ($config['check_budget_per_hour'] ?? 300),
            'check_cache_ttl' => (int) ($config['check_cache_ttl'] ?? 300),
            'list_page_size' => (int) ($config['list_page_size'] ?? 100),
            'stale_after_hours' => (int) ($config['stale_after_hours'] ?? 24),
        ];
    }

    /** Allowlistete Basis-URL für die Umgebung. */
    public static function endpointUrl(DomainProviderEnvironment $environment): string {
        $endpoints = self::resolve()['endpoints'];

        return rtrim($endpoints[$environment->value] ?? $endpoints['production'], '/');
    }

    /** Vollständige call.cgi-URL für die Umgebung. */
    public static function callUrl(DomainProviderEnvironment $environment): string {
        return self::endpointUrl($environment) . self::resolve()['call_path'];
    }
}
