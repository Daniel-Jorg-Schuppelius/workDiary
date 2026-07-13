<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GithubConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Github;

use App\Models\{Organization, PluginSetting};

/**
 * Effektive GitHub-Konfiguration je Organisation (Muster SevDeskConfig):
 *
 *   1. plugin_settings (verschlüsselt) der Organisation
 *   2. config('plugins.github.*') als Fallback (ENV / Tests / Konsole)
 *
 * Leere Strings zählen als „nicht gesetzt" (leere encrypted-Strings ⇒ null,
 * daher konsequent `!empty()`/`?:` statt `??`). Die API-Basis-URL ist bewusst
 * NICHT org-konfigurierbar (kein SSRF-Vektor; GitHub Enterprise wäre ein
 * eigener Ausbaupunkt).
 */
class GithubConfig {
    /**
     * @return array{api_token: ?string, repo_owner: ?string, repo_name: ?string, webhook_secret: ?string, default_project: ?string, base_url: string, enabled: bool}
     */
    public static function resolve(?int $organizationId = null): array {
        $out = [
            'api_token' => self::stringOrNull(config('plugins.github.api_token')),
            'repo_owner' => self::stringOrNull(config('plugins.github.repo_owner')),
            'repo_name' => self::stringOrNull(config('plugins.github.repo_name')),
            'webhook_secret' => null,
            'default_project' => null,
            'base_url' => rtrim((string) config('plugins.github.base_url', 'https://api.github.com'), '/'),
            'enabled' => (bool) config('plugins.github.enabled', false),
        ];

        $organizationId ??= self::boundOrganizationId();
        if ($organizationId === null) {
            return $out;
        }

        $row = PluginSetting::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', GithubPlugin::ID)
            ->first();
        if ($row === null) {
            return $out;
        }

        $out['enabled'] = (bool) $row->enabled;
        $settings = $row->settings ?? [];
        foreach (['api_token', 'repo_owner', 'repo_name', 'webhook_secret', 'default_project'] as $key) {
            if (! empty($settings[$key])) {
                $out[$key] = trim((string) $settings[$key]);
            }
        }

        return $out;
    }

    /** Vollständig genug für Import/Healthcheck (Token + Repo-Koordinaten)? */
    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return $config['api_token'] !== null && $config['repo_owner'] !== null && $config['repo_name'] !== null;
    }

    private static function stringOrNull(mixed $value): ?string {
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' ? $value : null;
    }

    private static function boundOrganizationId(): ?int {
        if (! app()->bound('currentOrganization')) {
            return null;
        }

        $organization = app('currentOrganization');

        return $organization instanceof Organization ? (int) $organization->id : null;
    }
}
