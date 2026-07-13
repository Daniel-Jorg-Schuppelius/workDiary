<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GitlabConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Gitlab;

use App\Models\{Organization, PluginSetting};

/**
 * Effektive GitLab-Konfiguration je Organisation (Muster SevDeskConfig):
 *
 *   1. plugin_settings (verschlüsselt) der Organisation
 *   2. config('plugins.gitlab.*') als Fallback (ENV / Tests / Konsole)
 *
 * Leere Strings zählen als „nicht gesetzt" (leere encrypted-Strings ⇒ null,
 * daher konsequent `!empty()`/`?:` statt `??`). Die Instanz-URL IST
 * org-konfigurierbar (self-hosted/On-Premise) — der SSRF-Guard sitzt in der
 * {@see \App\Plugins\Gitlab\Api\GitlabClientFactory}.
 */
class GitlabConfig {
    /**
     * @return array{api_token: ?string, project_id: ?string, webhook_token: ?string, default_project: ?string, base_url: string, allow_private_network: bool, enabled: bool}
     */
    public static function resolve(?int $organizationId = null): array {
        $out = [
            'api_token' => self::stringOrNull(config('plugins.gitlab.api_token')),
            'project_id' => self::stringOrNull(config('plugins.gitlab.project_id')),
            'webhook_token' => null,
            'default_project' => null,
            'base_url' => rtrim((string) config('plugins.gitlab.base_url', 'https://gitlab.com'), '/'),
            'allow_private_network' => (bool) config('plugins.gitlab.allow_private_network', false),
            'enabled' => (bool) config('plugins.gitlab.enabled', false),
        ];

        $organizationId ??= self::boundOrganizationId();
        if ($organizationId === null) {
            return $out;
        }

        $row = PluginSetting::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', GitlabPlugin::ID)
            ->first();
        if ($row === null) {
            return $out;
        }

        $out['enabled'] = (bool) $row->enabled;
        $settings = $row->settings ?? [];
        foreach (['api_token', 'project_id', 'webhook_token', 'default_project'] as $key) {
            if (! empty($settings[$key])) {
                $out[$key] = trim((string) $settings[$key]);
            }
        }
        if (! empty($settings['base_url'])) {
            $out['base_url'] = rtrim(trim((string) $settings['base_url']), '/');
        }
        if (array_key_exists('allow_private_network', $settings)) {
            $out['allow_private_network'] = filter_var($settings['allow_private_network'], FILTER_VALIDATE_BOOL);
        }

        return $out;
    }

    /** Vollständig genug für Import/Healthcheck (Token + Projekt-ID)? */
    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return $config['api_token'] !== null && $config['project_id'] !== null;
    }

    private static function stringOrNull(mixed $value): ?string {
        $value = is_string($value) || is_numeric($value) ? trim((string) $value) : '';

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
