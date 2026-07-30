<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BuchhaltungsButlerServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\BuchhaltungsButler;

use App\Models\Invoice;
use App\Plugins\BuchhaltungsButler\Observers\BhbInvoiceObserver;
use App\Plugins\BuchhaltungsButler\Services\BhbOutboxDispatcher;
use App\Plugins\Support\PluginServiceProviderBase;
use App\Services\Integration\IntegrationOutboxDispatcherResolver;

/**
 * Bootet das BuchhaltungsButler-Plugin (MVP-432): Config-Defaults, Invoice-
 * Observer (Statuswechsel → Outbox-Enqueue) und Outbox-Dispatcher. Keine
 * eigenen Routen/Views — Konfiguration über die Auto-Form der Plugin-Karte.
 */
class BuchhaltungsButlerServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return BuchhaltungsButlerPlugin::ID;
    }

    protected function bootPlugin(): void {
        Invoice::observe(BhbInvoiceObserver::class);
        app(IntegrationOutboxDispatcherResolver::class)->register(new BhbOutboxDispatcher());
    }
}
