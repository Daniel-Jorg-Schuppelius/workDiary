<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EasybillServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Easybill;

use App\Enums\Finance\BillingMode;
use App\Enums\Migration\MigrationProvider;
use App\Plugins\Easybill\Console\EasybillSyncCommand;
use App\Plugins\Easybill\Services\{EasybillArticleCatalogSource, EasybillPhoneContactSource, EasybillTarget, EasybillVoucherLinks, EasybillVoucherPullService};
use App\Plugins\Easybill\Services\Retainer\{EasybillRetainerPublisher, EasybillRetainerVouchers};
use App\Plugins\Support\PluginServiceProviderBase;
use App\Services\AccountingMigration\{MigrationSources, MirroredVoucherMigrationSource};
use App\Services\Billing\Feed\DocumentFeedSourceRegistry;
use App\Services\Billing\RetainerChannelResolver;
use App\Services\Finance\Accounting\Vouchers\VoucherPullerRegistry;
use App\Services\Finance\Targets\FacturationTargetRegistry;
use App\Services\Platform\Catalog\ArticleCatalog;

/**
 * Bootet das easybill-Plugin (MVP-431): Config-Defaults unter
 * `plugins.easybill.*` + Sync-Command für den Beleg-Rückabruf. Einzige Route
 * ist das Original-PDF gespiegelter Belege (MVP-1499) — Konfiguration über
 * die Auto-Form der Plugin-Karte, die Übergabe über den
 * {@see \App\Plugins\Easybill\Services\EasybillTarget}.
 */
class EasybillServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return EasybillPlugin::ID;
    }

    protected function registerPlugin(): void {
        // Kontakt-Rückweg bei „Buchhaltung führt“ (MVP-1502).
        $this->commands([EasybillSyncCommand::class, Console\EasybillPullContactsCommand::class]);
        // Rufnummernabgleich (FRITZ!Box, CTI; MVP-1500).
        $this->app->scoped(EasybillPhoneContactSource::class);
        $this->app->tag([EasybillPhoneContactSource::class], 'external-phone-contact-sources');
    }

    protected function bootPlugin(): void {
        // Faktura-Übergabe (MVP-1032): der Kern kennt das Ziel nur über die Registry.
        $this->app->make(FacturationTargetRegistry::class)->register(EasybillTarget::class);

        // Beleg-Rückabruf (MVP-1031): der Kern kennt die Anbindung nur über die Registry.
        $this->app->make(VoucherPullerRegistry::class)->register(EasybillVoucherPullService::class);
        // Artikelkatalog (Feature 185, gap-09): easybill-Artikel erreichen den Kern nur über diese Quelle.
        $this->app->make(ArticleCatalog::class)->register($this->app->make(EasybillArticleCatalogSource::class));
        // Original-PDF gespiegelter Belege in Belegfluss und Akte (MVP-1499).
        $this->app->make(DocumentFeedSourceRegistry::class)->register(new EasybillVoucherLinks);
        // Quelle eines Buchhaltungswechsels über den Belegspiegel (Feature 110, MVP-1506).
        $this->app->make(MigrationSources::class)->register(new MirroredVoucherMigrationSource(MigrationProvider::Easybill));
        // Pauschal-Modus (Feature 098, MVP-1505): Pauschale und Spitzabrechnung in easybill, Zahlstand aus dem Spiegel.
        $this->app->make(RetainerChannelResolver::class)->register(
            EasybillPlugin::ID,
            BillingMode::Easybill->programName(),
            fn (): EasybillRetainerPublisher => $this->app->make(EasybillRetainerPublisher::class),
            fn (): EasybillRetainerVouchers => $this->app->make(EasybillRetainerVouchers::class),
        );

        // Belegnummern beim Übertragen (Phase 145).
        $this->app->make(\App\Modules\ModuleRegistry::class)->contribute(\App\Services\Numbering\Contracts\DocumentIssuer::class, \App\Plugins\Easybill\Services\EasybillDocumentIssuer::class);
        // Zugehörige Belege (MVP-1158).
        $this->app->make(\App\Modules\ModuleRegistry::class)->contribute(\App\Services\Billing\Contracts\DocumentRelationSource::class, \App\Plugins\Easybill\Services\EasybillRelationSource::class);
        // Kontaktsuche im Zuordnen-Dialog des Rechnungseingangs (Phase 152, MVP-1226).
        $this->app->make(\App\Modules\ModuleRegistry::class)->contribute(\App\Services\Invoicing\Contracts\IncomingPartyLookup::class, \App\Plugins\Easybill\Services\EasybillIncomingPartyLookup::class);
        // Rechnungen mit Nachweisen durch WorkDiary zustellen, das Fakturasystem führt (Feature 196).
        $this->app->make(\App\Modules\ModuleRegistry::class)->contribute(\App\Services\Finance\Contracts\ExternalInvoiceProvider::class, \App\Plugins\Easybill\Services\EasybillExternalInvoiceProvider::class);
        $this->app->make(\App\Modules\ModuleRegistry::class)->contribute(\App\Services\Integration\Contracts\OwnershipConflictReplay::class, Services\EasybillContactImportService::class);
        $this->app->make(\App\Modules\ModuleRegistry::class)->contribute(\App\Services\Integration\Contracts\OwnershipConflictReplay::class, Services\EasybillDocumentPullService::class);
    }
}
