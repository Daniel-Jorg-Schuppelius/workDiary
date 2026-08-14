<?php

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
            'delivery_format' => ['required', Rule::enum(InvoiceDeliveryFormat::class)],
        ]);

        /** @var Customer $customer */
        $customer = Customer::query()->findOrFail($data['customer_id']);
        if (app(BillingModeResolver::class)->effectiveFor($customer)->isExternal()) {
            return back()->withInput()->with('error', __('invoice-import.error.external_billing'));
        }

        $file = $request->file('file');
        $extension = mb_strtolower($file->getClientOriginalExtension());
        $allowedExtensions = ['pdf', 'docx', 'doc', 'xlsx', 'xls'];
        $allowedMimes = [
            'application/pdf',
            'application/zip',
            'application/x-zip-compressed',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/msword',
            'application/vnd.ms-excel',
            'application/x-ole-storage',
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

        try {
            $extracted = $extractor->extract((string) $file->getRealPath(), $extension);
        } catch (\Throwable) {
            return back()->withInput()->with('error', __('invoice-import.error.unreadable'));
        }
        if ((int) ($extracted['text_length'] ?? 0) === 0) {
            return back()->withInput()->with('error', __('invoice-import.error.no_text'));
        }

        /** @var User $actor */
        $actor = Auth::user();
        $organization = $customer->organization()->firstOrFail();
        $tax = app(TaxResolver::class)->resolve($organization, $customer);
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

        $invoice = DB::transaction(function () use (
            $actor,
            $customer,
            $currency,
            $data,
            $detectedNumber,
            $documents,
            $extracted,
            $extension,
            $file,
            $net,
            $number,
            $numberAlreadyUsed,
            $sha256,
            $tax,
            $taxRate,
        ): Invoice {
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
                'is_reverse_charge' => $tax['reverse_charge'],
                'buyer_reference' => $extracted['buyer_reference'] ?? $customer->buyer_reference,
                'delivery_format' => $data['delivery_format'],
                'created_by' => $actor->id,
                'import_metadata' => [
                    'source' => $extension,
                    'sha256' => $sha256,
                    'original_name' => $file->getClientOriginalName(),
                    'extraction' => $extracted,
                ],
            ]);

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
            $invoice->update(['import_metadata' => $metadata]);
            $invoice->audit('invoice.document_imported', [
                'document_id' => $document->id,
                'sha256' => $sha256,
                'confidence' => $extracted['confidence'] ?? 0,
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
        $documentId = (int) data_get($invoice->import_metadata, 'document_id', 0);
        $document = Document::query()
            ->whereKey($documentId)
            ->where('documentable_type', $invoice->getMorphClass())
            ->where('documentable_id', $invoice->id)
            ->firstOrFail();

        return redirect()->route('documents.download', $document);
    }
}
