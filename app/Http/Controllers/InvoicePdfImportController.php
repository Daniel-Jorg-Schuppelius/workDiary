<?php
/*
 * Created on   : Fri Aug 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoicePdfImportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Document\DocumentType;
use App\Enums\Invoicing\InvoiceDeliveryFormat;
use App\Models\{Customer, Document, Invoice, User};
use App\Services\Document\DocumentService;
use App\Services\Finance\BillingModeResolver;
use App\Services\Invoicing\{InvoiceGenerator, InvoicePdfImportService, TaxResolver};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, DB, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** PDF-/Word-/Excel-Rechnung → prüfbarer Entwurf → E-Rechnungs-Ausgabe. */
class InvoicePdfImportController extends Controller {
    public function create(): View {
        Gate::authorize('create', Invoice::class);

        return view('invoices._pdf_import_dialog', [
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'buyer_reference']),
            'formats' => InvoiceDeliveryFormat::cases(),
        ]);
    }

    public function store(
        Request $request,
        InvoicePdfImportService $extractor,
        InvoiceGenerator $generator,
        DocumentService $documents,
    ): RedirectResponse {
        Gate::authorize('create', Invoice::class);

        $request->merge([
            'customer_id' => \App\Support\Sqid::decodeOrNumeric(Customer::class, $request->input('customer_id')),
        ]);
        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'customer_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization('customers')],
            'delivery_format' => ['nullable', Rule::enum(InvoiceDeliveryFormat::class)],
        ]);

        /** @var Customer $customer */
        $customer = Customer::query()->findOrFail($data['customer_id']);
        // Ohne explizite Wahl gilt der Kunden-Default, sonst PDF.
        $deliveryFormat = InvoiceDeliveryFormat::tryFrom((string) ($data['delivery_format'] ?? ''))
            ?? $customer->delivery_format
            ?? InvoiceDeliveryFormat::Pdf;
        if (app(BillingModeResolver::class)->effectiveFor($customer)->isExternal()) {
            return back()->withInput()->with('error', __('invoice-import.error.external_billing'));
        }

        $file = $request->file('file');
        $extension = mb_strtolower($file->getClientOriginalExtension());
        $allowedExtensions = ['pdf', 'docx', 'doc', 'xlsx', 'xls', 'xml'];
        $allowedMimes = [
            'application/pdf',
            'application/zip',
            'application/x-zip-compressed',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/msword',
            'application/vnd.ms-excel',
            'application/x-ole-storage',
            'application/xml',
            'text/xml',
        ];
        if (! in_array($extension, $allowedExtensions, true)
            || ! in_array((string) $file->getMimeType(), $allowedMimes, true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' => __('invoice-import.error.unsupported_format'),
            ]);
        }

        $bytes = (string) file_get_contents((string) $file->getRealPath());
        $sha256 = CryptoHelper::hash($bytes);
        $duplicate = Invoice::query()
            ->where('organization_id', $customer->organization_id)
            ->where('import_metadata->sha256', $sha256)
            ->first();
        if ($duplicate !== null) {
            return redirect()->route('invoices.show', $duplicate)
                ->with('error', __('invoice-import.error.duplicate'));
        }

        $organization = $customer->organization()->firstOrFail();
        try {
            $extracted = $extractor->extract((string) $file->getRealPath(), $extension, (string) $file->getMimeType(), $organization);
        } catch (\Throwable) {
            return back()->withInput()->with('error', __('invoice-import.error.unreadable'));
        }
        $structured = ($extracted['structured'] ?? false) === true;
        if (! $structured && $extension === 'xml') {
            return back()->withInput()->with('error', __('invoice-import.error.xml_not_einvoice'));
        }
        if (! $structured && (int) ($extracted['text_length'] ?? 0) === 0) {
            return back()->withInput()->with('error', __('invoice-import.error.no_text'));
        }

        /** @var User $actor */
        $actor = Auth::user();
        $tax = app(TaxResolver::class)->resolve($organization, $customer);

        // Gegenprobe mit den eigenen Stammdaten: bei einer hochgeladenen
        // EIGENEN Rechnung müssen IBAN/USt-IdNr. zur Organisation passen —
        // Abweichung wird sichtbar eskaliert (z. B. falsche Datei erwischt).
        if (! $structured) {
            $einvoiceSeller = (array) data_get($organization->settings, 'einvoice', []);
            $extractedIban = \CommonToolkit\Helper\Data\BankHelper::normalizeIBAN((string) data_get($extracted, 'payment.iban'));
            $orgIban = \CommonToolkit\Helper\Data\BankHelper::normalizeIBAN((string) ($einvoiceSeller['iban'] ?? ''));
            if ($extractedIban !== null && $orgIban !== null && $extractedIban !== $orgIban) {
                $extracted['warnings'][] = 'seller_iban_mismatch';
            }
            $extractedVat = strtoupper((string) preg_replace('/\s+/', '', (string) ($extracted['seller_vat'] ?? '')));
            $orgVat = strtoupper((string) preg_replace('/\s+/', '', (string) ($einvoiceSeller['vat_id'] ?? '')));
            if ($extractedVat !== '' && $orgVat !== '' && $extractedVat !== $orgVat) {
                $extracted['warnings'][] = 'seller_vat_mismatch';
            }
        }
        $detectedNumber = trim((string) ($extracted['number'] ?? ''));
        $numberAlreadyUsed = $detectedNumber !== '' && Invoice::query()
            ->where('organization_id', $customer->organization_id)
            ->where('number', $detectedNumber)
            ->exists();
        $number = $detectedNumber !== '' && ! $numberAlreadyUsed
            ? $detectedNumber
            : $generator->nextNumber((int) $customer->organization_id);
        if ($numberAlreadyUsed) {
            $extracted['warnings'][] = 'duplicate_number';
        }

        $currency = CurrencyCode::tryFrom((string) ($extracted['currency'] ?? 'EUR')) ?? CurrencyCode::Euro;
        $net = max(0.0, (float) ($extracted['net'] ?? 0));
        $taxRate = $extracted['tax_rate'] ?? $tax['rate'];
        $reverseCharge = $structured ? (bool) ($extracted['is_reverse_charge'] ?? false) : $tax['reverse_charge'];

        $invoice = DB::transaction(function () use (
            $actor,
            $customer,
            $currency,
            $deliveryFormat,
            $detectedNumber,
            $documents,
            $extracted,
            $extension,
            $file,
            $net,
            $number,
            $numberAlreadyUsed,
            $reverseCharge,
            $sha256,
            $taxRate,
        ): Invoice {
            $skonto = is_array($extracted['skonto'] ?? null) ? $extracted['skonto'] : null;
            $invoice = Invoice::query()->create([
                'organization_id' => $customer->organization_id,
                'customer_id' => $customer->id,
                'number' => $number,
                'external_number' => $numberAlreadyUsed ? $detectedNumber : null,
                'number_source' => 'file_import',
                'status' => Invoice::STATUS_DRAFT,
                'issued_on' => $extracted['issued_on'] ?? null,
                'due_on' => $extracted['due_on'] ?? null,
                'currency' => $currency->value,
                'tax_rate' => $taxRate,
                'is_reverse_charge' => $reverseCharge,
                'buyer_reference' => $extracted['buyer_reference'] ?? $customer->buyer_reference,
                'delivery_format' => $deliveryFormat,
                'payment_terms_days' => $extracted['payment_terms_days'] ?? null,
                'skonto_percent' => $skonto['percent'] ?? null,
                'skonto_days' => $skonto['days'] ?? null,
                'discount_amount' => $extracted['document_discount'] ?? null,
                'created_by' => $actor->id,
                'import_metadata' => [
                    'source' => $extension,
                    'sha256' => $sha256,
                    'original_name' => $file->getClientOriginalName(),
                    'extraction' => $extracted,
                ],
            ]);

            $lines = is_array($extracted['lines'] ?? null) ? $extracted['lines'] : [];
            if ($lines !== []) {
                foreach ($lines as $line) {
                    $invoice->items()->create([
                        'organization_id' => $invoice->organization_id,
                        'service_date' => $extracted['service_date'] ?? null,
                        'description' => (string) $line['description'],
                        'quantity' => (string) $line['quantity'],
                        'unit' => (string) $line['unit'],
                        'unit_price' => (string) $line['unit_price'],
                        'tax_rate' => $line['tax_rate'],
                        'tax_category' => $line['tax_category'] ?? null,
                        'discount_amount' => $line['discount_amount'] ?? null,
                        'position' => (int) $line['position'],
                    ]);
                }
            } else {
                $invoice->items()->create([
                    'organization_id' => $invoice->organization_id,
                    'service_date' => $extracted['issued_on'] ?? null,
                    'description' => __('invoice-import.default_line', ['number' => $detectedNumber !== '' ? $detectedNumber : $number]),
                    'quantity' => '1.000',
                    'unit' => 'Stk.',
                    'unit_price' => number_format($net, 4, '.', ''),
                    'tax_rate' => $taxRate,
                    'position' => 1,
                ]);
            }
            $invoice->load('items');
            $invoice->recalculate();
            $invoice->save();

            $document = $documents->create($invoice, $actor, [
                'title' => __('invoice-import.source_title', ['number' => $number]),
                'document_type' => DocumentType::Invoice->value,
                'description' => __('invoice-import.source_description'),
                'confidential' => true,
            ], $file);

            $metadata = (array) $invoice->import_metadata;
            $metadata['document_id'] = $document->id;
            // Nachkalkulations-Gegenprobe: weicht die lokale Neuberechnung vom
            // erkannten Brutto ab, wird das sichtbar eskaliert — nie still.
            if (($extracted['gross'] ?? null) !== null && $invoice->total !== null
                && abs((float) $invoice->total->getAmount() - (float) $extracted['gross']) > 0.02) {
                $metadata['extraction']['warnings'][] = 'totals_recalculated_mismatch';
            }
            $invoice->update(['import_metadata' => $metadata]);
            $invoice->audit('invoice.document_imported', [
                'document_id' => $document->id,
                'sha256' => $sha256,
                'confidence' => $extracted['confidence'] ?? 0,
                'structured' => ($extracted['structured'] ?? false) === true,
                'lines' => count($lines),
            ]);

            return $invoice;
        });

        return redirect()->route('invoices.show', $invoice)
            ->with('status', __('invoice-import.success'));
    }

    public function edit(Invoice $invoice): View {
        Gate::authorize('update', $invoice);

        return view('invoices._einvoice_options_dialog', [
            'invoice' => $invoice,
            'formats' => InvoiceDeliveryFormat::cases(),
            'currencies' => CurrencyCode::cases(),
        ]);
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse {
        Gate::authorize('update', $invoice);

        $data = $request->validate([
            'number' => [
                'required', 'string', 'max:64',
                Rule::unique('invoices', 'number')
                    ->where(fn($query) => $query->where('organization_id', $invoice->organization_id))
                    ->ignore($invoice->id),
            ],
            'issued_on' => ['nullable', 'date'],
            'due_on' => ['nullable', 'date', 'after_or_equal:issued_on'],
            'currency' => ['required', Rule::enum(CurrencyCode::class)],
            'buyer_reference' => ['nullable', 'string', 'max:100'],
            'delivery_format' => ['required', Rule::enum(InvoiceDeliveryFormat::class)],
        ]);

        $invoice->update($data);
        $invoice->audit('invoice.einvoice_options_updated', [
            'delivery_format' => $data['delivery_format'],
        ]);

        return redirect()->route('invoices.show', $invoice)
            ->with('status', __('invoice-import.options_saved'));
    }

    public function source(Invoice $invoice): RedirectResponse {
        Gate::authorize('view', $invoice);

        return redirect()->route('documents.download', $this->sourceDocument($invoice));
    }

    /** Import-Prüfschritt: Original ↔ erkannte Werte/Positionen nebeneinander. */
    public function review(Invoice $invoice): View {
        Gate::authorize('update', $invoice);
        $metadata = (array) $invoice->import_metadata;
        abort_unless(is_array($metadata['extraction'] ?? null), 404);
        $invoice->loadMissing(['items', 'customer']);

        return view('invoices.import-review', [
            'invoice' => $invoice,
            'extraction' => (array) $metadata['extraction'],
            'source' => (string) ($metadata['source'] ?? ''),
            'reviewed' => (bool) ($metadata['reviewed'] ?? false),
            'hasPreview' => in_array((string) ($metadata['source'] ?? ''), ['pdf', 'xml'], true),
        ]);
    }

    /** Bestätigte Prüfung: nur ein auditiertes Flag — Werte ändert der Dialog. */
    public function confirmReview(Invoice $invoice): RedirectResponse {
        Gate::authorize('update', $invoice);
        $metadata = (array) $invoice->import_metadata;
        abort_unless(is_array($metadata['extraction'] ?? null), 404);

        $metadata['reviewed'] = true;
        $metadata['reviewed_by'] = (int) Auth::id();
        $metadata['reviewed_at'] = now()->toIso8601String();
        $invoice->update(['import_metadata' => $metadata]);
        $invoice->audit('invoice.import_review_confirmed', [
            'document_id' => data_get($metadata, 'document_id'),
        ]);

        return redirect()->route('invoices.show', $invoice)
            ->with('status', __('invoice-import.review_confirmed'));
    }

    /** Inline-Vorschau des Originals (documents.download erzwingt Attachment). */
    public function sourcePreview(Invoice $invoice): \Symfony\Component\HttpFoundation\BinaryFileResponse {
        Gate::authorize('view', $invoice);
        $document = $this->sourceDocument($invoice);
        $version = $document->currentVersion;
        abort_if($version === null, 404);
        $disk = \Illuminate\Support\Facades\Storage::disk($version->disk);
        abort_unless($disk->exists($version->path), 404);

        // Vertrauliches Dokument: Zugriff wie beim Download auditiert
        // (gleiches Event/gleiche Ersteller-Ausnahme wie DocumentController).
        /** @var User|null $viewer */
        $viewer = Auth::user();
        if ($viewer !== null && $document->confidential && (int) $document->created_by_user_id !== (int) $viewer->id) {
            \App\Models\AuditLog::query()->create([
                'organization_id' => $document->organization_id,
                'user_id' => $viewer->id,
                'event' => 'document.confidentialAccessed',
                'auditable_type' => Document::class,
                'auditable_id' => $document->id,
                'changes' => ['title' => $document->title],
            ]);
        }

        return response()->file($disk->path($version->path), [
            'Content-Disposition' => 'inline; filename="' . addcslashes($version->original_name, '"\\') . '"',
        ]);
    }

    private function sourceDocument(Invoice $invoice): Document {
        $documentId = (int) data_get($invoice->import_metadata, 'document_id', 0);

        return Document::query()
            ->whereKey($documentId)
            ->where('documentable_type', $invoice->getMorphClass())
            ->where('documentable_id', $invoice->id)
            ->firstOrFail();
    }
}
