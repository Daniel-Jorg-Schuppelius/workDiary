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

use App\Plugins\Support\PluginSettingsResolver;

/**
 * Effektive GitHub-Konfiguration je Organisation: plugin_settings vor
 * config('plugins.github.*') — Lookup/Cast im {@see PluginSettingsResolver}
 * (C10). Leere Strings zählen als „nicht gesetzt" (leere encrypted-Strings
 * ⇒ null). Die API-Basis-URL ist bewusst NICHT org-konfigurierbar (kein
 * SSRF-Vektor; GitHub Enterprise wäre ein eigener Ausbaupunkt);
 * webhook_secret/default_project kommen nur aus den Org-Settings.
 */
class GithubConfig {
    /**
     * @return array{api_token: ?string, repo_owner: ?string, repo_name: ?string, webhook_secret: ?string, default_project: ?string, base_url: string, writeback: bool, enabled: bool}
     */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(GithubPlugin::ID, $organizationId);

        return [
            'api_token' => $r->string('api_token', trim: true),
            'repo_owner' => $r->string('repo_owner', trim: true),
            'repo_name' => $r->string('repo_name', trim: true),
            'webhook_secret' => $r->settingString('webhook_secret', trim: true),
            'default_project' => $r->settingString('default_project', trim: true),
            'base_url' => rtrim((string) config('plugins.github.base_url', 'https://api.github.com'), '/'),
            'writeback' => $r->bool('writeback', false),
            'enabled' => $r->enabled(),
        ];
    }

    /** Vollständig genug für Import/Healthcheck (Token + Repo-Koordinaten)? */
    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return $config['api_token'] !== null && $config['repo_owner'] !== null && $config['repo_name'] !== null;
    }
}
