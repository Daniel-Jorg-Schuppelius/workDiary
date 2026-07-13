<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebdavServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Webdav;

use App\Plugins\Support\Mirror\{MirrorOutboxDispatcher, MirrorTargetRegistry};
use App\Plugins\Webdav\Contracts\WebdavGatewayFactory;
use App\Plugins\Webdav\Services\GuzzleWebdavGatewayFactory;
use App\Services\Integration\IntegrationOutboxDispatcherResolver;
use Illuminate\Support\ServiceProvider;

/**
 * Plugin-eigener ServiceProvider (Feature 058, MVP-127). Bindet die
 * Gateway-Factory (Tests ersetzen sie durch eine Fake-Variante ohne HTTP),
 * registriert Config/Routen/Views/Command und meldet das WebDAV-Ziel am
 * gemeinsamen Spiegel-Kern an (MVP-330, Bauturbo A10): die Registry hängt
 * die Freigabe-Observer an, der generische Outbox-Dispatcher übernimmt die
 * Zustellung.
 */
class WebdavServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'plugins.' . WebdavPlugin::ID);

        $this->app->singleton(WebdavGatewayFactory::class, GuzzleWebdavGatewayFactory::class);
        // Geteilte Ziel-Registry des Spiegel-Kerns (A10): ein Singleton für
        // alle Mirror-Plugins — Observer/Dispatcher sehen dieselben Targets.
        $this->app->singletonIf(MirrorTargetRegistry::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\WebdavMirrorCommand::class,
            ]);
        }
    }

    public function boot(): void {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadViewsFrom(__DIR__ . '/Resources/views', 'webdav');

        $target = new WebdavMirrorTarget();
        $this->app->make(MirrorTargetRegistry::class)->register($target);
        $this->app->make(IntegrationOutboxDispatcherResolver::class)->register(new MirrorOutboxDispatcher($target));
    }
}
