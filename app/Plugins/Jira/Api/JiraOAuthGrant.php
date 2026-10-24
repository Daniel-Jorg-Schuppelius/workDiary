<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JiraOAuthGrant.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Jira\Api;

use App\Plugins\Jira\JiraConfig;
use App\Plugins\Support\PluginOAuthGrant;

/** Atlassian-3LO-Grant der installationsweiten Jira-App; Test-Austauschpunkt als Container-Singleton. */
class JiraOAuthGrant extends PluginOAuthGrant {
    /** @return array<string, string|int|bool> */
    protected function config(): array {
        return JiraConfig::resolve();
    }

    protected function callbackRouteName(): string {
        return 'admin.jira.oauth.callback';
    }
}
