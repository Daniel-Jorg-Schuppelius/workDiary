<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JiraConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Jira;

/**
 * Jira-Konfiguration: die App-Registrierung ist installationsweit (verteilbare
 * Atlassian-App des Herausgebers), deshalb ohne Overlay je Organisation.
 */
final class JiraConfig {
    /** @return array{client_id: string, client_secret: string, authorize_url: string, token_url: string, api_base: string, scopes: string, page_size: int, max_pages: int} */
    public static function resolve(): array {
        return [
            'client_id' => trim((string) config('plugins.jira.client_id', '')),
            'client_secret' => trim((string) config('plugins.jira.client_secret', '')),
            'authorize_url' => (string) config('plugins.jira.authorize_url'),
            'token_url' => (string) config('plugins.jira.token_url'),
            'api_base' => rtrim((string) config('plugins.jira.api_base'), '/'),
            'scopes' => (string) config('plugins.jira.scopes'),
            'page_size' => max(1, min(100, (int) config('plugins.jira.page_size', 100))),
            'max_pages' => max(1, (int) config('plugins.jira.max_pages', 50)),
        ];
    }

    public static function isConfigured(): bool {
        $config = self::resolve();

        return $config['client_id'] !== '' && $config['client_secret'] !== '';
    }
}
