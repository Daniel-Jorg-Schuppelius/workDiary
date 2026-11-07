<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalInvoiceDocumentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoicing\{StoreSupportingDocumentLinkRequest, StoreSupportingDocumentUploadRequest};
use App\Models\Finance\ExternalInvoice;
use App\Models\Invoicing\InvoiceSupportingDocument;
use App\Services\Invoicing\EInvoice\SupportingDocumentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Gate, Storage};
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Nachweise einer extern ausgestellten Rechnung (Feature 196): derselbe Dienst
 * wie an der lokalen Rechnung; eingefroren wird mit der ersten Versandfassung.
 */
class ExternalInvoiceDocumentController extends Controller {
    private const ROUTES = 'finance.external-invoices.documents';

    public function __construct(private readonly SupportingDocumentService $documents) {}

    public function uploadForm(ExternalInvoice $externalInvoice): View {
        Gate::authorize('update', $externalInvoice);

        return $this->dialog($externalInvoice, 'upload');
    }

    public function linkForm(ExternalInvoice $externalInvoice): View {
        Gate::authorize('update', $externalInvoice);

        return $this->dialog($externalInvoice, 'link');
    }

    public function upload(StoreSupportingDocumentUploadRequest $request, ExternalInvoice $externalInvoice): RedirectResponse {
        Gate::authorize('update', $externalInvoice);
        $file = $request->file('file');
        abort_unless($file instanceof \Illuminate\Http\UploadedFile, 422);

        $this->documents->addUpload($externalInvoice, $file, $request->validated('description'), $this->authUser());

        return $this->back($externalInvoice, 'invoicing.supporting_documents.status.uploaded');
    }

    public function link(StoreSupportingDocumentLinkRequest $request, ExternalInvoice $externalInvoice): RedirectResponse {
        Gate::authorize('update', $externalInvoice);

        $this->documents->addLink($externalInvoice, (string) $request->validated('url'), $request->validated('description'), $this->authUser());

        return $this->back($externalInvoice, 'invoicing.supporting_documents.status.linked');
    }

    public function generate(ExternalInvoice $externalInvoice): RedirectResponse {
        Gate::authorize('update', $externalInvoice);

        $created = $this->documents->generateActivityReport($externalInvoice, $this->authUser());

        return $this->back($externalInvoice, $created->isEmpty() ? 'invoicing.supporting_documents.status.generated_none' : 'invoicing.supporting_documents.status.generated');
    }

    public function move(Request $request, ExternalInvoice $externalInvoice, InvoiceSupportingDocument $document): RedirectResponse {
        Gate::authorize('update', $externalInvoice);
        $this->assertBelongs($externalInvoice, $document);
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        $this->documents->move($document, $direction === 'up' ? -1 : 1);

        return $this->back($externalInvoice, 'invoicing.supporting_documents.status.moved');
    }

    public function destroy(ExternalInvoice $externalInvoice, InvoiceSupportingDocument $document): RedirectResponse {
        Gate::authorize('update', $externalInvoice);
        $this->assertBelongs($externalInvoice, $document);

        $this->documents->remove($document);

        return $this->back($externalInvoice, 'invoicing.supporting_documents.status.removed');
    }

    public function download(ExternalInvoice $externalInvoice, InvoiceSupportingDocument $document): StreamedResponse {
        Gate::authorize('view', $externalInvoice);
        $this->assertBelongs($externalInvoice, $document);
        $attachment = $document->attachment;
        abort_if($attachment === null || ! Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, (string) $document->filename, ['Content-Type' => (string) $document->mime]);
    }

    private function dialog(ExternalInvoice $invoice, string $mode): View {
        return view('invoices._supporting_document_dialog', [
            'carrier' => $invoice,
            'routePrefix' => self::ROUTES,
            'eyebrow' => __('finance.external_invoice.eyebrow', ['number' => $invoice->displayNumber()]),
            'mode' => $mode,
        ]);
    }

    private function assertBelongs(ExternalInvoice $invoice, InvoiceSupportingDocument $document): void {
        abort_unless((int) $document->external_invoice_id === (int) $invoice->id, 404);
    }

    private function back(ExternalInvoice $invoice, string $message): RedirectResponse {
        return redirect()->route('finance.external-invoices.show', $invoice)->with('status', __($message));
    }
}
