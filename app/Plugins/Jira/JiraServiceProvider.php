<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JiraServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Jira;

use App\Plugins\Jira\Api\JiraOAuthGrant;
use App\Plugins\Support\PluginServiceProviderBase;

/** Bootet den Jira-Import; der Grant ist Singleton, damit Tests den Token-Endpunkt ersetzen können. */
class JiraServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return JiraPlugin::ID;
    }

    protected function registerPlugin(): void {
        $this->app->singleton(JiraOAuthGrant::class, static fn (): JiraOAuthGrant => new JiraOAuthGrant());
        $this->commands([Console\JiraSyncCommand::class]);
    }
}
