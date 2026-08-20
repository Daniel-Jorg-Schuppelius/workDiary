<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GithubServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Github;

use App\Plugins\Support\PluginServiceProviderBase;

/**
 * Plugin-eigener ServiceProvider (Feature 060, Bauturbo A6). Wird vom Core-
 * {@see \App\Providers\PluginServiceProvider} geladen, sobald GithubPlugin in
 * der Registry steht: Config-Defaults, Webhook-Route und Polling-Command.
 * Der HTTP-Transport kommt aus der {@see \App\Plugins\Support\PluginHttpFactory}
 * (php-api-toolkit) — Tests ersetzen ihn durch FakePluginHttp.
 */
class GithubServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return GithubPlugin::ID;
    }

    protected function registerPlugin(): void {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\GithubSyncCommand::class,
            ]);
        }
    }

    protected function bootPlugin(): void {
        // Status-Rückrichtung (Audit 2026-08, Welle 1.4): erledigte Aufgabe
        // schließt das GitHub-Issue über die Integrations-Outbox.
        $this->app->make(\App\Services\Integration\IntegrationOutboxDispatcherResolver::class)
            ->register(new Services\GithubIssueWritebackDispatcher);
    }
}
