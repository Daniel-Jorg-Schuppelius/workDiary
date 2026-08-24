<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice;

use App\Plugins\Lexoffice\Console\{LexofficeSyncArticlesCommand, LexofficeSyncContactsCommand, LexofficeSyncVouchersCommand, LexofficeWebhooksCommand};
use App\Plugins\Support\PluginServiceProviderBase;
use App\Services\Billing\Feed\DocumentFeedSourceRegistry;

/**
 * Plugin-eigener ServiceProvider. Wird vom Core-{@see \App\Providers\PluginServiceProvider}
 * geladen sobald die LexofficePlugin-Klasse in der Plugin-Registry steht.
 *
 * Verantwortlichkeiten:
 *  - Container-Bindings (LexofficeService, LexofficeContactSync, LexofficeInvoiceService)
 *  - Lazy-Loading der Plugin-Settings aus der DB (statt aus config())
 *  - Routes + Views aus dem Plugin-Verzeichnis registrieren
 *  - Artisan-Commands
 *  - Migrations
 */
class LexofficeServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return LexofficePlugin::ID;
    }

    protected function registerPlugin(): void {
        $this->app->singleton(LexofficeService::class, function (): LexofficeService {
            $config = LexofficeConfig::resolve();

            return new LexofficeService(
                apiKey: $config['api_key'],
                mapper: new LexofficeMapper,
                defaults: $config['defaults'],
                baseUrl: $config['base_url'],
            );
        });

        $this->app->singleton(LexofficeContactSync::class, function (): LexofficeContactSync {
            return new LexofficeContactSync;
        });

        $this->app->singleton(LexofficeInvoiceService::class, function (): LexofficeInvoiceService {
            $config = LexofficeConfig::resolve();

            return new LexofficeInvoiceService(
                mapper: new LexofficeInvoiceMapper,
                apiKey: $config['api_key'],
                defaults: $config['defaults'],
                baseUrl: $config['base_url'],
            );
        });
    }

    protected function bootPlugin(): void {
        // Belegfluss-Quelle (Feature 105; Vollscan B9): der Kern kennt die
        // Tabelle `lexoffice_vouchers` nur noch über diese Registrierung.
        $this->app->make(DocumentFeedSourceRegistry::class)
            ->register(new LexofficeDocumentFeedSource);

        if ($this->app->runningInConsole()) {
            $this->commands([
                LexofficeSyncArticlesCommand::class,
                LexofficeSyncContactsCommand::class,
                LexofficeSyncVouchersCommand::class,
                LexofficeWebhooksCommand::class,
            ]);
        }
    }
}
