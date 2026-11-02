<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceNinjaServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\InvoiceNinja;

use App\Plugins\InvoiceNinja\Services\{InvoiceNinjaArticleCatalogSource, InvoiceNinjaVoucherLinks, InvoiceNinjaVoucherPullService};
use App\Plugins\Support\PluginServiceProviderBase;
use App\Services\Billing\Feed\DocumentFeedSourceRegistry;
use App\Services\Finance\Accounting\Vouchers\VoucherPullerRegistry;
use App\Services\Platform\Catalog\ArticleCatalog;

class InvoiceNinjaServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return InvoiceNinjaPlugin::ID;
    }

    protected function bootPlugin(): void {
        // Beleg-Rückabruf: der Kern kennt die Anbindung nur über die Registry.
        $this->app->make(VoucherPullerRegistry::class)->register(InvoiceNinjaVoucherPullService::class);
        // Link zum Original im Belegfluss.
        $this->app->make(DocumentFeedSourceRegistry::class)->register(new InvoiceNinjaVoucherLinks);
        // Artikelkatalog (Feature 185, gap-09): Produkte erreichen den Kern nur über diese Quelle.
        $this->app->make(ArticleCatalog::class)->register($this->app->make(InvoiceNinjaArticleCatalogSource::class));
    }
}
