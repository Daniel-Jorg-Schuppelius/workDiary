<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodoistOAuth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Todoist\Api;

use App\Plugins\Support\PluginOAuthGrant;
use App\Plugins\Todoist\TodoistConfig;

/**
 * OAuth2-Authorization-Code-Grant für Todoist (Feature 055, MVP-111).
 * Todoist unterstützt kein PKCE — der Admin-Flow startet den Handshake
 * mit `withPkce: false`.
 */
class TodoistOAuth extends PluginOAuthGrant {
    /** @return array<string, string|int|bool> */
    protected function config(): array {
        return TodoistConfig::resolve();
    }

    protected function callbackRouteName(): string {
        return 'admin.todoist.oauth.callback';
    }
}
