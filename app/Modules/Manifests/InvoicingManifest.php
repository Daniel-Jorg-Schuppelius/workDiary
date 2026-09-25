<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoicingManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Faktura und E-Rechnung“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class InvoicingManifest extends Manifest {
    public function code(): string {
        return 'invoicing';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Core;
    }

    public function label(): string {
        return 'Faktura und E-Rechnung';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Invoicing',
            'Peppol',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'base_interest_rates',
            'incoming_einvoices',
            'invoice_commissions',
            'invoice_item_time_entries',
            'invoice_items',
            'invoice_mail_templates',
            'invoice_retentions',
            'invoice_schedule_items',
            'invoice_schedule_runs',
            'invoice_schedules',
            'invoices',
            'peppol_participant_lookups',
            'text_corrections',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::Invoicing,
        ];
    }

    /** @return list<string> */
    public function plugins(): array {
        return [
            'peppol-access-point',
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            \App\Services\Demo\Contracts\DemoBlock::class => [
                \App\Services\Invoicing\Demo\InvoicingDemoBlock::class,
            ],
            \App\Plugins\Support\Mirror\Contracts\MirrorPdfRenderer::class => [
                \App\Services\Invoicing\Mirror\InvoiceMirrorPdf::class,
            ],
            \App\Services\Import\EntitySpec::class => [
                \App\Services\Invoicing\Import\InvoiceSpec::class,
            ],
            \App\Services\Document\Contracts\MailableDocumentProvider::class => [
                \App\Services\Invoicing\Mail\QuoteMailDocument::class,
            ],
            \App\Services\Mail\Contracts\MailIntakeHandler::class => [
                \App\Services\Invoicing\Mail\EInvoiceMailIntakeHandler::class,
            ],
        ];
    }

    /** @return array<class-string, class-string> */
    public function contracts(): array {
        return [
            \App\Services\Invoicing\Contracts\PaymentStatusProvider::class => \App\Services\Invoicing\Contracts\NullPaymentStatusProvider::class,
            \App\Services\Invoicing\Contracts\InvoiceGaebExporter::class => \App\Services\Invoicing\Contracts\NullInvoiceGaebExporter::class,
        ];
    }
}
