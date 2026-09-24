<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ConstructionNoticeMailDocument.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Construction\Mail;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Construction\ConstructionNotice;
use App\Models\Customer\Customer;
use App\Services\Construction\{ConstructionNoticePdfRenderer, ConstructionNoticeService};
use App\Services\Document\Contracts\MailableDocumentProvider;
use Illuminate\Database\Eloquent\Model;

/**
 * VOB/B-Schreiben (Feature 062, MVP-728) im Belegversand: Der Versand IST der
 * Zweck — erst er erzeugt den Zugangsnachweis und schreibt das Schreiben fest.
 */
final class ConstructionNoticeMailDocument implements MailableDocumentProvider {
    public function __construct(
        private readonly ConstructionNoticePdfRenderer $renderer,
        private readonly ConstructionNoticeService $notices,
    ) {}

    public function kinds(): array {
        return array_values(array_filter(RenderDocumentKind::cases(), static fn(RenderDocumentKind $kind): bool => str_starts_with($kind->value, 'construction_')));
    }

    public function modelClass(RenderDocumentKind $kind): string {
        return ConstructionNotice::class;
    }

    public function pdfBytes(Model $document, RenderDocumentKind $kind): string {
        /** @var ConstructionNotice $document */
        return $this->renderer->render($document);
    }

    public function attachmentFilename(Model $document, RenderDocumentKind $kind): string {
        /** @var ConstructionNotice $document */
        return $this->renderer->filename($document) . '.pdf';
    }

    public function documentNumber(Model $document, RenderDocumentKind $kind): string {
        /** @var ConstructionNotice $document */
        return $document->displayNo();
    }

    public function variables(Model $document, RenderDocumentKind $kind): array {
        /** @var ConstructionNotice $document */
        $document->loadMissing(['customer', 'project', 'site']);

        return [
            'customer_name' => (string) ($document->recipient_name ?: ($document->customer->name ?? '')),
            'customer_email' => (string) ($document->recipient_email ?: ($document->customer->email ?? '')),
            'document_number' => $document->displayNo(),
            'document_date' => $document->occurred_on->format('d.m.Y'),
            'document_subject' => (string) $document->subject,
            'project_name' => (string) ($document->project->name ?? $document->site->name ?? ''),
            'legal_reference' => (string) ($document->legal_reference ?? ''),
        ];
    }

    public function defaultRecipient(Model $document, RenderDocumentKind $kind): string {
        /** @var ConstructionNotice $document */
        return (string) ($document->recipient_email ?: ($document->customer->email ?? ''));
    }

    public function localeCustomer(Model $document, RenderDocumentKind $kind): ?Customer {
        /** @var ConstructionNotice $document */
        return $document->customer;
    }

    public function afterSent(Model $document, RenderDocumentKind $kind): void {
        /** @var ConstructionNotice $document */
        $this->notices->markSent($document);
    }
}
