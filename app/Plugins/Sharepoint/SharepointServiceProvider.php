<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SharepointServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Sharepoint;

use App\Plugins\Sharepoint\Api\SharepointOAuth;
use App\Plugins\Support\Mirror\{MirrorOutboxDispatcher, MirrorTargetRegistry};
use App\Plugins\Support\PluginServiceProviderBase;
use App\Services\Integration\IntegrationOutboxDispatcherResolver;

/**
 * Plugin-eigener ServiceProvider (MVP-330, Bauturbo A10). Registriert
 * Config-Defaults, Routen, Views und den Spiegel-Command; meldet das
 * SharePoint-Ziel am gemeinsamen Spiegel-Kern an (Registry hängt die
 * Freigabe-Observer an, der generische Outbox-Dispatcher übernimmt die
 * Zustellung). {@see SharepointOAuth} ist Singleton — Tests ersetzen ihn
 * durch eine Variante mit Guzzle-MockHandler (A8-Muster).
 */
class SharepointServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return SharepointPlugin::ID;
    }

    protected function registerPlugin(): void {
        $this->app->singleton(SharepointOAuth::class, fn(): SharepointOAuth => new SharepointOAuth());
        // Geteilte Ziel-Registry des Spiegel-Kerns (A10): ein Singleton für
        // alle Mirror-Plugins — Observer/Dispatcher sehen dieselben Targets.
        $this->app->singletonIf(MirrorTargetRegistry::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\SharepointMirrorCommand::class,
            ]);
        }
    }

    protected function bootPlugin(): void {
        $target = new SharepointMirrorTarget();
        $this->app->make(MirrorTargetRegistry::class)->register($target);
        $this->app->make(IntegrationOutboxDispatcherResolver::class)->register(new MirrorOutboxDispatcher($target));
    }
}
