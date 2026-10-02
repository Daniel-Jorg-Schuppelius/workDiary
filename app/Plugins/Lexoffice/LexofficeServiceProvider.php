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

use App\Plugins\Lexoffice\Console\{LexofficeMaterializeVoucherFilesCommand, LexofficeRepairResaleLinksCommand, LexofficeSyncArticlesCommand, LexofficeSyncContactsCommand, LexofficeSyncVoucherCategoriesCommand, LexofficeSyncVoucherLinesCommand, LexofficeSyncVouchersCommand, LexofficeWebhooksCommand};
use App\Plugins\Lexoffice\Services\{LexofficeArticleCatalogSource, LexofficeInvoiceDraftTarget, LexofficeInvoiceMirrorSource, LexofficeMaterialProvider, LexofficePartyDocumentSource, LexofficePurchaseDocumentSource, LexofficeRevenueSource, LexofficeSpendSource, LexofficeTarget};
use App\Plugins\Lexoffice\Services\Retainer\{LexofficeRetainerPublisher, LexofficeRetainerVouchers};
use App\Plugins\Support\PluginServiceProviderBase;
use App\Services\Billing\{BillingModeResolver, ExpenseLinkProviderResolver, ExternalPurchaseSources, ExternalRevenueSources, PartyDocumentSources, RetainerChannelResolver};
use App\Services\Billing\Feed\DocumentFeedSourceRegistry;
use App\Services\Billing\Purchase\PurchaseDocuments;
use App\Services\Finance\Targets\FacturationTargetRegistry;
use App\Services\Invoicing\TaxResolver;
use App\Services\Material\MaterialProviderRegistry;
use App\Services\Platform\Catalog\ArticleCatalog;
use App\Services\Reselling\Draft\InvoiceDraftTargets;
use App\Services\Reselling\Mirror\InvoiceMirror;

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
        $this->app->scoped(LexofficePhoneContactSource::class);
        $this->app->tag([LexofficePhoneContactSource::class], 'external-phone-contact-sources');

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
        // Quelle eines Buchhaltungswechsels (MVP-1048).
        $this->app->make(\App\Services\AccountingMigration\MigrationSources::class)->register(new \App\Plugins\Lexoffice\Services\LexofficeMigrationSource);
        // Sessionloser Webhook (Audit 2026-08, Welle 1.3): Bursts erlauben, Flooding deckeln;
        // Verluste heilt der geplante Pull-Sync.
        \Illuminate\Support\Facades\RateLimiter::for('lexoffice-webhook', fn (\Illuminate\Http\Request $request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(120)->by('lwh:' . $request->ip()));
        // Faktura-Übergabe (MVP-1032): der Kern kennt das Ziel nur über die Registry.
        $this->app->make(FacturationTargetRegistry::class)->register(LexofficeTarget::class);

        // Umsatz aus dem Beleg-Spiegel für Auswertungen (MVP-1034/1035).
        $this->app->make(ExternalRevenueSources::class)->register(new LexofficeRevenueSource);
        $this->app->make(ExternalPurchaseSources::class)->register(new LexofficeSpendSource);
        // Belegbilder löscht die endgültige Löschung der Organisation mit (MVP-1044).
        $this->app->make(\App\Services\Org\OrganizationFileTables::class)->register('lexoffice_vouchers', ['path' => 'file_path']);
        // Gespiegelte Artikel in der Kennungsprüfung `identifiers:audit` (MVP-1044).
        $this->app->make(\App\Services\Stammdaten\IdentifierAuditModels::class)->register(\App\Plugins\Lexoffice\Models\LexofficeArticle::class);
        // Belegliste der Kunden- und Lieferantenakte (MVP-1038).
        $this->app->make(PartyDocumentSources::class)->register(new LexofficePartyDocumentSource);

        // Material-Suche (MVP-1033) mit den Zugangsdaten der aktuellen Organisation.
        $this->app->make(MaterialProviderRegistry::class)->register('lexoffice', static function (): ?LexofficeMaterialProvider {
            $config = LexofficeConfig::resolve();

            return is_string($config['api_key']) && $config['api_key'] !== ''
                ? new LexofficeMaterialProvider($config['api_key'], (string) $config['base_url'])
                : null;
        });

        // Belegfluss-Quelle (Feature 105; Vollscan B9): der Kern kennt die
        // Tabelle `lexoffice_vouchers` nur noch über diese Registrierung.
        $this->app->make(DocumentFeedSourceRegistry::class)
            ->register(new LexofficeDocumentFeedSource);

        // Auslagen-Belege (Feature 105/106; Vollscan B9, Entscheid E8: der
        // aktive Push bleibt Lexoffice-only) — der Kern spricht nur noch das
        // ExpenseLinkProvider-Interface.
        $this->app->make(ExpenseLinkProviderResolver::class)
            ->register(LexofficePlugin::ID, fn(): LexofficeExpenseLinkProvider => new LexofficeExpenseLinkProvider);

        // Belegspiegel des Reselling-Registers (Feature 152, Review 2026-09-10):
        // der Kern liest Lexoffice-Positionen nur noch über diese Quelle.
        $this->app->make(InvoiceMirror::class)
            ->register(new LexofficeInvoiceMirrorSource);

        // Artikelkatalog (Phase 125, MVP-1025): Lexoffice-Artikel erreichen den Kern nur über diese Quelle.
        $this->app->make(ArticleCatalog::class)
            ->register(new LexofficeArticleCatalogSource);

        // Pauschalen des Retainer-Modus (MVP-1027): Push und Belegabgleich im Plugin.
        $this->app->make(RetainerChannelResolver::class)->register(
            LexofficePlugin::ID,
            'Lexoffice',
            function (): LexofficeRetainerPublisher {
                // Zugangsdaten gelten je Organisation; der Monatslauf wechselt sie.
                $this->app->forgetInstance(LexofficeInvoiceService::class);

                return $this->app->make(LexofficeRetainerPublisher::class);
            },
            fn (): LexofficeRetainerVouchers => $this->app->make(LexofficeRetainerVouchers::class),
        );

        // Eingangsbelege des Reselling-Registers (Review 2026-09-11, Einkauf):
        // Lexoffice-Eingangsbelege erreichen den Kern nur über diese Quelle.
        $this->app->make(PurchaseDocuments::class)
            ->register(new LexofficePurchaseDocumentSource);

        // Entwurfsziel des Reselling-Registers (Review 2026-09-11): der Kern
        // schiebt Rechnungsvorschläge bei Lexoffice-Hoheit nur über dieses Ziel.
        $this->app->make(InvoiceDraftTargets::class)
            ->register(new LexofficeInvoiceDraftTarget($this->app->make(TaxResolver::class), $this->app->make(BillingModeResolver::class)));

        $this->commands([
            LexofficeSyncArticlesCommand::class,
            LexofficeSyncContactsCommand::class,
            LexofficeSyncVouchersCommand::class,
            LexofficeSyncVoucherLinesCommand::class,
            LexofficeSyncVoucherCategoriesCommand::class,
            LexofficeRepairResaleLinksCommand::class,
            LexofficeMaterializeVoucherFilesCommand::class,
            LexofficeWebhooksCommand::class,
        ]);
    }
}
