<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceSupportingDocumentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoicing\{StoreSupportingDocumentLinkRequest, StoreSupportingDocumentUploadRequest};
use App\Models\Invoicing\{Invoice, InvoiceSupportingDocument};
use App\Services\Invoicing\EInvoice\SupportingDocumentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Gate, Storage};
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Rechnungsbegründende Unterlagen einer Rechnung (Feature 196, MVP-1485). */
class InvoiceSupportingDocumentController extends Controller {
    public function __construct(private readonly SupportingDocumentService $documents) {}

    public function uploadForm(Invoice $invoice): View {
        Gate::authorize('update', $invoice);

        return view('invoices._supporting_document_dialog', ['carrier' => $invoice, 'routePrefix' => 'invoices.supporting-documents', 'eyebrow' => $invoice->documentLabel() . ' ' . $invoice->number, 'mode' => 'upload']);
    }

    public function linkForm(Invoice $invoice): View {
        Gate::authorize('update', $invoice);

        return view('invoices._supporting_document_dialog', ['carrier' => $invoice, 'routePrefix' => 'invoices.supporting-documents', 'eyebrow' => $invoice->documentLabel() . ' ' . $invoice->number, 'mode' => 'link']);
    }

    public function upload(StoreSupportingDocumentUploadRequest $request, Invoice $invoice): RedirectResponse {
        Gate::authorize('update', $invoice);
        $file = $request->file('file');
        abort_unless($file instanceof \Illuminate\Http\UploadedFile, 422);

        $this->documents->addUpload($invoice, $file, $request->validated('description'), $this->authUser());

        return $this->back($invoice, 'invoicing.supporting_documents.status.uploaded');
    }

    public function link(StoreSupportingDocumentLinkRequest $request, Invoice $invoice): RedirectResponse {
        Gate::authorize('update', $invoice);

        $this->documents->addLink($invoice, (string) $request->validated('url'), $request->validated('description'), $this->authUser());

        return $this->back($invoice, 'invoicing.supporting_documents.status.linked');
    }

    public function generate(Invoice $invoice): RedirectResponse {
        Gate::authorize('update', $invoice);

        $created = $this->documents->generateActivityReport($invoice, $this->authUser());

        return $this->back($invoice, $created->isEmpty() ? 'invoicing.supporting_documents.status.generated_none' : 'invoicing.supporting_documents.status.generated');
    }

    public function move(Request $request, Invoice $invoice, InvoiceSupportingDocument $document): RedirectResponse {
        Gate::authorize('update', $invoice);
        $this->assertBelongs($invoice, $document);
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        $this->documents->move($document, $direction === 'up' ? -1 : 1);

        return $this->back($invoice, 'invoicing.supporting_documents.status.moved');
    }

    public function destroy(Invoice $invoice, InvoiceSupportingDocument $document): RedirectResponse {
        Gate::authorize('update', $invoice);
        $this->assertBelongs($invoice, $document);

        $this->documents->remove($document);

        return $this->back($invoice, 'invoicing.supporting_documents.status.removed');
    }

    public function download(Invoice $invoice, InvoiceSupportingDocument $document): StreamedResponse {
        Gate::authorize('view', $invoice);
        $this->assertBelongs($invoice, $document);
        $attachment = $document->attachment;
        abort_if($attachment === null || ! Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, (string) $document->filename, ['Content-Type' => (string) $document->mime]);
    }

    private function assertBelongs(Invoice $invoice, InvoiceSupportingDocument $document): void {
        abort_unless((int) $document->invoice_id === (int) $invoice->id, 404);
    }

    private function back(Invoice $invoice, string $message): RedirectResponse {
        return redirect()->route('invoices.show', $invoice)->with('status', __($message));
    }
}
