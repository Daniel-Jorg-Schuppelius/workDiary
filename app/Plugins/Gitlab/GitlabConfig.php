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

use App\Plugins\Support\PluginSettingsResolver;

/**
 * Effektive GitLab-Konfiguration je Organisation: plugin_settings vor
 * config('plugins.gitlab.*') — Lookup/Cast im {@see PluginSettingsResolver}
 * (C10). Leere Strings zählen als „nicht gesetzt" (leere encrypted-Strings
 * ⇒ null). Die Instanz-URL IST org-konfigurierbar (self-hosted/On-Premise) —
 * der SSRF-Guard sitzt in der {@see \App\Plugins\Gitlab\Api\GitlabClientFactory};
 * webhook_token/default_project kommen nur aus den Org-Settings.
 */
class GitlabConfig {
    /**
     * @return array{api_token: ?string, project_id: ?string, webhook_token: ?string, default_project: ?string, base_url: string, allow_private_network: bool, writeback: bool, enabled: bool}
     */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(GitlabPlugin::ID, $organizationId);

        return [
            'api_token' => $r->string('api_token', trim: true),
            'project_id' => $r->string('project_id', trim: true),
            'webhook_token' => $r->settingString('webhook_token', trim: true),
            'default_project' => $r->settingString('default_project', trim: true),
            'base_url' => rtrim($r->string('base_url', trim: true) ?? 'https://gitlab.com', '/'),
            'allow_private_network' => $r->bool('allow_private_network', false),
            'writeback' => $r->bool('writeback', false),
            'enabled' => $r->enabled(),
        ];
    }

    /** Vollständig genug für Import/Healthcheck (Token + Projekt-ID)? */
    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return $config['api_token'] !== null && $config['project_id'] !== null;
    }
}
