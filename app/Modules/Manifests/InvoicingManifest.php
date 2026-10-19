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
            'Recognition',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'base_interest_rates',
            'incoming_einvoices',
            'invoice_sender_rules',
            'incoming_einvoice_transfers',
            'incoming_invoice_templates',
            'invoice_commissions',
            'invoice_inquiries',
            'invoice_item_time_entries',
            'invoice_items',
            'invoice_mail_templates',
            'invoice_payment_links',
            'invoice_retentions',
            'invoice_schedule_items',
            'invoice_schedule_runs',
            'invoice_schedules',
            'invoices',
            'online_payments',
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
            'mollie',
            'peppol-access-point',
            'stripe',
            'sumup',
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            // Posteingangsregeln (Phase 150, MVP-1209/1210) und Aufräumen abgelehnter Eingänge (MVP-1213).
            \App\Automation\Triggers\RuleTrigger::class => [
                \App\Services\Invoicing\Automation\IncomingInvoiceReceivedTrigger::class,
            ],
            \App\Automation\Actions\RuleAction::class => [
                \App\Services\Invoicing\Automation\DiscardIncomingInvoiceAction::class,
                \App\Services\Invoicing\Automation\RejectIncomingInvoiceAction::class,
                \App\Services\Invoicing\Automation\AssignIncomingInvoiceAction::class,
            ],
            \App\Services\Retention\Contracts\RetentionPolicyProvider::class => [
                \App\Services\Invoicing\Retention\InvoicingRetentionPolicies::class,
            ],
            \App\Services\Billing\Contracts\DocumentRelationSource::class => [
                \App\Services\Invoicing\Relations\InvoiceRelationSource::class,
            ],
            \App\Services\Mcp\Contracts\McpTool::class => [
                \App\Services\Invoicing\Mcp\InvoicesTool::class,
                \App\Services\Invoicing\Mcp\OpenItemsTool::class,
                \App\Services\Invoicing\Mcp\CreateInvoiceDraftTool::class,
                \App\Services\Invoicing\Mcp\DunningProposalTool::class,
            ],
            \App\Services\Billing\Contracts\DocumentChainSource::class => [
                \App\Services\Invoicing\Chain\OverdueInvoices::class,
            ],
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
                \App\Services\Invoicing\Mail\InvoiceInquiryMailIntakeHandler::class,
            ],
            // Rechnungsnachfragen (MVP-1200): Klärfälle in der Inbox mit Kandidaten markieren.
            \App\Services\Mail\Contracts\MailIntakeAnnotator::class => [
                \App\Services\Invoicing\Mail\InvoiceInquiryMailIntakeHandler::class,
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

    public function listeners(): array {
        return [
            \App\Events\Article\ArticlesMerged::class => [
                \App\Listeners\Invoicing\RepointMergedServiceArticles::class,
            ],
            // Rechnungseingang (MVP-1111): nach der Zuordnung an die Buchhaltung.
            // Die Antwort auf die Mail einer Rechnungsnachfrage beantwortet die Nachfrage (Feature 166).
            \App\Events\Communication\CommunicationNoteReplied::class => [
                \App\Listeners\Invoicing\AnswerInquiryOnNoteReply::class,
            ],
            \App\Events\Invoicing\IncomingInvoiceAssigned::class => [
                \App\Listeners\Invoicing\TransferAssignedIncomingInvoice::class,
            ],
        ];
    }
}
