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

use App\Models\{Document, Invoice, Protocol};
use App\Plugins\Webdav\Contracts\WebdavGatewayFactory;
use App\Plugins\Webdav\Observers\{DocumentMirrorObserver, InvoiceMirrorObserver, ProtocolMirrorObserver};
use App\Plugins\Webdav\Services\{GuzzleWebdavGatewayFactory, WebdavOutboxDispatcher};
use App\Services\Integration\IntegrationOutboxDispatcherResolver;
use Illuminate\Support\ServiceProvider;

/**
 * Plugin-eigener ServiceProvider (Feature 058, MVP-127). Bindet die
 * Gateway-Factory (Tests ersetzen sie durch eine Fake-Variante ohne HTTP),
 * registriert Config/Routen/Views/Command, hängt den Document-Observer ein
 * (Freigabe → Outbox) und meldet den Outbox-Dispatcher an der Registry an.
 */
class WebdavServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'plugins.' . WebdavPlugin::ID);

        $this->app->singleton(WebdavGatewayFactory::class, GuzzleWebdavGatewayFactory::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\WebdavMirrorCommand::class,
            ]);
        }
    }

    public function boot(): void {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadViewsFrom(__DIR__ . '/Resources/views', 'webdav');

        Document::observe(DocumentMirrorObserver::class);
        Invoice::observe(InvoiceMirrorObserver::class);       // Rang 19: finalisierte Rechnungen
        Protocol::observe(ProtocolMirrorObserver::class);     // Rang 19: signierte Protokolle
        $this->app->make(IntegrationOutboxDispatcherResolver::class)->register(new WebdavOutboxDispatcher());
    }
}
