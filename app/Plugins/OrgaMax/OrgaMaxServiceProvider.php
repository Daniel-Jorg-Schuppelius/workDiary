<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax;

use App\Plugins\OrgaMax\Console\OrgaMaxSyncCommand;
use App\Plugins\OrgaMax\Services\{OrgaMaxArticleCatalogSource, OrgaMaxOutboxDispatcher, OrgaMaxPhoneContactSource, OrgaMaxTarget, OrgaMaxVoucherLinks, OrgaMaxVoucherPullService};
use App\Plugins\Support\PluginServiceProviderBase;
use App\Services\Billing\Feed\DocumentFeedSourceRegistry;
use App\Services\Finance\Accounting\Vouchers\VoucherPullerRegistry;
use App\Services\Finance\Targets\FacturationTargetRegistry;
use App\Services\Integration\IntegrationOutboxDispatcherResolver;
use App\Services\Platform\Catalog\ArticleCatalog;

/**
 * Bootet das orgaMAX-Plugin (Feature 077): Routen, Views, Console-Command
 * und die Registrierung des Outbox-Dispatchers für Schreibbefehle
 * (invoice.convert / invoice.send / payment.push).
 */
class OrgaMaxServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return OrgaMaxPlugin::ID;
    }

    protected function registerPlugin(): void {
        $this->commands([OrgaMaxSyncCommand::class]);
        // Rufnummernabgleich (FRITZ!Box, CTI; MVP-1500).
        $this->app->scoped(OrgaMaxPhoneContactSource::class);
        $this->app->tag([OrgaMaxPhoneContactSource::class], 'external-phone-contact-sources');
    }

    protected function bootPlugin(): void {
        // Quelle eines Buchhaltungswechsels (MVP-1048).
        $this->app->make(\App\Services\AccountingMigration\MigrationSources::class)->register(new \App\Plugins\OrgaMax\Services\OrgaMaxMigrationSource);
        // Faktura-Übergabe (MVP-1032): der Kern kennt das Ziel nur über die Registry.
        $this->app->make(FacturationTargetRegistry::class)->register(OrgaMaxTarget::class);

        $this->app->make(IntegrationOutboxDispatcherResolver::class)
            ->register($this->app->make(OrgaMaxOutboxDispatcher::class));

        // Belegspiegel (MVP-1501): Rechnungen und Ausgaben erreichen Belegfluss, Akte,
        // Umsatz, Einkauf und Planquelle über `accounting_vouchers`; `orgamax_invoices`
        // bleibt der Plugin-Spiegel für Wechsel, Belegverbund und PDF.
        $this->app->make(VoucherPullerRegistry::class)->register(OrgaMaxVoucherPullService::class);
        $this->app->make(ArticleCatalog::class)->register($this->app->make(OrgaMaxArticleCatalogSource::class));
        // Original-PDF gespiegelter Rechnungen in Belegfluss und Akte (MVP-670).
        $this->app->make(DocumentFeedSourceRegistry::class)->register(new OrgaMaxVoucherLinks);

        // Zugehörige Belege (MVP-1158).
        $this->app->make(\App\Modules\ModuleRegistry::class)->contribute(\App\Services\Billing\Contracts\DocumentRelationSource::class, \App\Plugins\OrgaMax\Services\OrgaMaxRelationSource::class);
        // Kontaktsuche im Zuordnen-Dialog des Rechnungseingangs (Phase 152, MVP-1226).
        $this->app->make(\App\Modules\ModuleRegistry::class)->contribute(\App\Services\Invoicing\Contracts\IncomingPartyLookup::class, \App\Plugins\OrgaMax\Services\OrgaMaxIncomingPartyLookup::class);
        // Phase 145 (MVP-1161/1164/1166): Angebot, AB und Rechnung nummeriert orgaMAX beim Festschreiben.
        $this->app->make(\App\Modules\ModuleRegistry::class)->contribute(\App\Services\Numbering\Contracts\DocumentIssuer::class, \App\Plugins\OrgaMax\Services\OrgaMaxDocumentIssuer::class);
        // Rechnungen mit Nachweisen durch WorkDiary zustellen, das Fakturasystem führt (Feature 196).
        $this->app->make(\App\Modules\ModuleRegistry::class)->contribute(\App\Services\Finance\Contracts\ExternalInvoiceProvider::class, \App\Plugins\OrgaMax\Services\OrgaMaxExternalInvoiceProvider::class);
    }
}
