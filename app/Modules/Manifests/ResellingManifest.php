<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResellingManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Reselling-Register“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class ResellingManifest extends Manifest {
    public function code(): string {
        return 'reselling';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Reselling-Register';
    }

    public function licenseCode(): string {
        return 'module.reselling';
    }

    public function description(): string {
        return 'Weiterverkaufte Abos (Lizenzen, Domains, Hosting) mit Haltern, Abrechnungsperioden und Rechnungsbezügen.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Reselling',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'resale_account_allocations',
            'resale_article_classifications',
            'resale_ignored_lines',
            'resale_imports',
            'resale_license_assignments',
            'resale_license_batches',
            'resale_license_keys',
            'resale_license_products',
            'resale_license_units',
            'resale_period_links',
            'resale_periods',
            'resale_plan_links',
            'resale_price_catalog',
            'resale_provider_suppliers',
            'resale_purchase_entries',
            'resale_subscriptions',
            'reselling_company_mappings',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'finance.resale.*',
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [],
            'items' => [
                'finance.resale.index',
            ],
            'groups' => [],
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            \App\Services\Cockpit\Contracts\CockpitPanel::class => [
                \App\Services\Reselling\Cockpit\ResaleCockpitPanel::class,
            ],
            \App\Services\WorkQueue\Contracts\WorkItemSource::class => [
                \App\Services\Reselling\Plan\PlanWorkItemSource::class,
            ],
            // Abo-Perioden als Übergabekanal der Finanzen (Feature 185, MVP-1422).
            \App\Services\Finance\Contracts\TransferSource::class => [
                \App\Services\Reselling\Register\ResalePeriodTransferSource::class,
            ],
        ];
    }

    /** @return list<string> Übergabe und Faktura-Ziele (Feature 045) für den Rechnungsvorschlag bei externer Hoheit. */
    public function requires(): array {
        return ['finance'];
    }

    /** @return array<class-string, list<class-string>> */
    public function listeners(): array {
        return [
            \App\Events\Article\ArticlesMerged::class => [
                \App\Listeners\Reselling\RepointMergedArticleRefs::class,
            ],
            // Feature 185 (MVP-1420): Eingangsrechnung eines Anbieter-Lieferanten wird Einkauf.
            \App\Events\Invoicing\IncomingInvoiceAssigned::class => [
                \App\Listeners\Reselling\ImportAssignedProviderInvoice::class,
            ],
        ];
    }

    /** @return array<class-string, class-string> */
    public function contracts(): array {
        return [
            \App\Services\Reselling\Contracts\ContractObligationSink::class => \App\Services\Reselling\Contracts\NullContractObligationSink::class,
            // Sammelzuordnung schreibt Domain-Halter in die Domainverwaltung (2026-10-27); ohne Domain-Modul neutral.
            \App\Services\Reselling\Contracts\DomainHolderWriter::class => \App\Services\Reselling\Contracts\NullDomainHolderWriter::class,
        ];
    }
}
