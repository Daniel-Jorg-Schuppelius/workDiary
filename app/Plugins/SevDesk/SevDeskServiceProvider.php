<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevDesk;

use App\Enums\Finance\BillingMode;
use App\Enums\Migration\MigrationProvider;
use App\Plugins\SevDesk\Services\Retainer\{SevDeskRetainerPublisher, SevDeskRetainerVouchers};
use App\Plugins\SevDesk\Services\{SevDeskArticleCatalogSource, SevDeskTarget, SevDeskVoucherLinks, SevDeskVoucherPullService};
use App\Plugins\Support\PluginServiceProviderBase;
use App\Services\AccountingMigration\{MigrationSources, MirroredVoucherMigrationSource};
use App\Services\Billing\Feed\DocumentFeedSourceRegistry;
use App\Services\Billing\RetainerChannelResolver;
use App\Services\Finance\Accounting\Vouchers\VoucherPullerRegistry;
use App\Services\Finance\Targets\FacturationTargetRegistry;
use App\Services\Platform\Catalog\ArticleCatalog;

/**
 * Bootet das sevDesk-Plugin (MVP-125): hängt die plugin-eigenen
 * Config-Defaults unter `plugins.sevdesk.*` ein. Einzige Route ist das
 * Original-PDF gespiegelter Rechnungen (MVP-1499) — Konfiguration läuft über
 * die Auto-Form der Plugin-Karte, die Übergabe über den
 * {@see \App\Plugins\SevDesk\Services\SevDeskTarget}.
 */
class SevDeskServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return SevDeskPlugin::ID;
    }

    protected function registerPlugin(): void {
        // Beleg-Rückabruf (Feature 122, MVP-611).
        // Kontakt-Rückweg bei „Buchhaltung führt“ (MVP-1502).
        $this->commands([
            Console\SevDeskPullVouchersCommand::class,
            Console\SevDeskPullContactsCommand::class,
        ]);
    }

    protected function bootPlugin(): void {
        // Faktura-Übergabe (MVP-1032): der Kern kennt das Ziel nur über die Registry.
        $this->app->make(FacturationTargetRegistry::class)->register(SevDeskTarget::class);

        // Beleg-Rückabruf (MVP-1031): der Kern kennt die Anbindung nur über die Registry.
        $this->app->make(VoucherPullerRegistry::class)->register(SevDeskVoucherPullService::class);
        // Artikelkatalog (Feature 185, gap-09): sevDesk-Artikel erreichen den Kern nur über diese Quelle.
        $this->app->make(ArticleCatalog::class)->register($this->app->make(SevDeskArticleCatalogSource::class));
        // Original-PDF gespiegelter Rechnungen in Belegfluss und Akte (MVP-1499).
        $this->app->make(DocumentFeedSourceRegistry::class)->register(new SevDeskVoucherLinks);
        // Quelle eines Buchhaltungswechsels über den Belegspiegel (Feature 110, MVP-1506).
        $this->app->make(MigrationSources::class)->register(new MirroredVoucherMigrationSource(MigrationProvider::SevDesk));
        // Pauschal-Modus (Feature 098, MVP-1505): Pauschale und Spitzabrechnung in sevDesk, Zahlstand aus dem Spiegel.
        $this->app->make(RetainerChannelResolver::class)->register(
            SevDeskPlugin::ID,
            BillingMode::SevDesk->programName(),
            fn (): SevDeskRetainerPublisher => $this->app->make(SevDeskRetainerPublisher::class),
            fn (): SevDeskRetainerVouchers => $this->app->make(SevDeskRetainerVouchers::class),
        );

        // Zugehörige Belege (MVP-1158).
        $this->app->make(\App\Modules\ModuleRegistry::class)->contribute(\App\Services\Billing\Contracts\DocumentRelationSource::class, \App\Plugins\SevDesk\Services\SevDeskRelationSource::class);
        // Kontaktsuche im Zuordnen-Dialog des Rechnungseingangs (Phase 152, MVP-1226).
        $this->app->make(\App\Modules\ModuleRegistry::class)->contribute(\App\Services\Invoicing\Contracts\IncomingPartyLookup::class, \App\Plugins\SevDesk\Services\SevDeskIncomingPartyLookup::class);
        // Phase 145 (MVP-1166/1167): Rechnung und Storno nummeriert sevDesk beim Ausstellen.
        $this->app->make(\App\Modules\ModuleRegistry::class)->contribute(\App\Services\Numbering\Contracts\DocumentIssuer::class, \App\Plugins\SevDesk\Services\SevDeskDocumentIssuer::class);
        // Rechnungseingang an sevDesk (Feature 163, MVP-1507): Tor, Journal und Wiederholung führt der Kern.
        $this->app->make(\App\Modules\ModuleRegistry::class)->contribute(\App\Services\Invoicing\Contracts\IncomingInvoiceTransferTarget::class, \App\Plugins\SevDesk\Services\SevDeskIncomingInvoiceTarget::class);
        // Rechnungen mit Nachweisen durch WorkDiary zustellen, das Fakturasystem führt (Feature 196).
        $this->app->make(\App\Modules\ModuleRegistry::class)->contribute(\App\Services\Finance\Contracts\ExternalInvoiceProvider::class, \App\Plugins\SevDesk\Services\SevDeskExternalInvoiceProvider::class);
    }
}
