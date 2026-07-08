<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\Document\DocumentType;
use App\Http\Controllers\Controller;
use App\Models\{Document, User};
use App\Services\Document\DocumentService;
use App\Services\Invoicing\EInvoice\IncomingEInvoiceService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate, Storage};
use Illuminate\View\View;

/**
 * Eingangs-E-Rechnung (Nachtrag 045b): Empfang + Visualisierung von
 * XRechnung/ZUGFeRD. Die Rechnung wird als Document (Typ Rechnung) im DMS
 * abgelegt — KEINE lokale Invoice (Rechnungshoheit beim externen
 * Faktura-Programm); die Detailseite parst das Original bei jedem Aufruf.
 */
class IncomingInvoiceController extends Controller {
    public function __construct(
        private readonly IncomingEInvoiceService $eInvoices,
        private readonly DocumentService $documents,
    ) {}

    public function index(): View {
        Gate::authorize('viewAny', Document::class);

        return view('finance.incoming-invoices.index', [
            'documents' => Document::query()
                ->where('document_type', DocumentType::Invoice->value)
                ->with(['currentVersion', 'creator'])
                ->orderByDesc('created_at')
                ->paginate(25),
            'canUpload' => Gate::allows('create', Document::class),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', Document::class);

        $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimetypes:application/xml,text/xml,text/plain,application/pdf'],
        ]);

        $file = $request->file('file');
        $contents = (string) file_get_contents((string) $file->getRealPath());

        // Inhaltsbasierter Hash + Dedup (MVP-165): identische Datei wird
        // je Organisation genau EINMAL angenommen.
        $sha256 = hash('sha256', $contents);
        $duplicate = \App\Models\IncomingEInvoice::query()->where('sha256', $sha256)->first();
        if ($duplicate !== null) {
            return redirect()->route('finance.incoming-invoices.show', $duplicate->document_id)
                ->with('error', __('Diese E-Rechnung wurde bereits am :date erfasst (Dublette).', [
                    'date' => $duplicate->received_at->isoFormat('L LT'),
                ]));
        }

        $parsed = $this->eInvoices->parse($contents, $file->getMimeType(), $file->getRealPath());
        if ($parsed === null) {
            return back()->with('error', __('Die Datei ist keine lesbare E-Rechnung (XRechnung/ZUGFeRD).'));
        }

        $summary = $this->eInvoices->summary($parsed);

        // Eingangs-Validierung (MVP-166): getrennt vom Original abgelegt.
        $extractedXml = $this->eInvoices->extractXml($contents, $file->getMimeType(), $file->getRealPath());
        $summary['validation'] = $extractedXml !== null
            ? $this->eInvoices->validateXml($extractedXml)
            : null;

        /** @var User $actor */
        $actor = Auth::user();
        $document = $this->documents->create(null, $actor, [
            'title' => __('E-Rechnung :number — :seller', [
                'number' => $summary['number'],
                'seller' => $summary['seller'] ?? '—',
            ]),
            'document_type' => DocumentType::Invoice->value,
            'description' => __(':profile · :gross :currency, fällig :due', [
                'profile' => $summary['profile'],
                'gross' => number_format((float) ($summary['gross'] ?? 0), 2, ',', '.'),
                'currency' => $summary['currency'],
                'due' => $summary['due_date'] ?? '—',
            ]),
        ], $file);

        // Prüfbereich-Datensatz (MVP-165/167): Hash, Herkunft, Empfangszeit.
        \App\Models\IncomingEInvoice::query()->create([
            'organization_id' => (int) $actor->organization_id,
            'document_id' => $document->id,
            'sha256' => $sha256,
            'source' => 'upload',
            'received_at' => now(),
            'summary' => $summary,
        ]);

        $document->audit('document.einvoice_received', [
            'number' => $summary['number'],
            'seller' => $summary['seller'],
            'gross' => $summary['gross'],
            'sha256' => $sha256,
        ]);

        return redirect()->route('finance.incoming-invoices.show', $document)
            ->with('success', __('E-Rechnung :number erfasst und im DMS abgelegt.', ['number' => $summary['number']]));
    }

    /**
     * Prüf-Entscheidung (MVP-167): Freigabe, Ablehnung, Rückfrage sowie
     * Zahlungsfreigabe (nur NACH fachlicher Freigabe). Keine automatische
     * Stammdatenänderung — reine Statusführung mit Audit.
     */
    public function decide(Request $request, \App\Models\IncomingEInvoice $incoming): RedirectResponse {
        abort_unless(Auth::user()?->canManageBilling() ?? false, 403);

        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected,question,payment_released'],
            'note' => ['nullable', 'string', 'max:500', 'required_if:decision,rejected'],
        ]);

        $target = $data['decision'];
        $allowed = match ($incoming->status) {
            \App\Models\IncomingEInvoice::STATUS_RECEIVED,
            \App\Models\IncomingEInvoice::STATUS_QUESTION => ['approved', 'rejected', 'question'],
            \App\Models\IncomingEInvoice::STATUS_APPROVED => ['payment_released', 'rejected'],
            default => [],
        };
        if (! in_array($target, $allowed, true)) {
            return back()->with('error', __('Übergang :from → :to ist nicht zulässig.', ['from' => $incoming->status, 'to' => $target]));
        }

        $incoming->update([
            'status' => $target,
            'decided_by' => (int) Auth::id(),
            'decided_at' => now(),
            'decision_note' => $data['note'] ?? null,
        ]);
        $incoming->audit('incoming_einvoice.decided', ['to' => $target]);

        return redirect()->route('finance.incoming-invoices.show', $incoming->document_id)
            ->with('success', __('Entscheidung gespeichert.'));
    }

    public function show(Document $document): View {
        Gate::authorize('view', $document);
        abort_unless($document->document_type === DocumentType::Invoice, 404);
        $incoming = \App\Models\IncomingEInvoice::query()->where('document_id', $document->id)->first();

        $version = $document->currentVersion;
        $parsed = null;
        if ($version !== null && Storage::disk('local')->exists((string) $version->path)) {
            $parsed = $this->eInvoices->parse(
                (string) Storage::disk('local')->get((string) $version->path),
                (string) $version->mime,
                Storage::disk('local')->path((string) $version->path),
            );
        }

        return view('finance.incoming-invoices.show', [
            'incoming' => $incoming,
            'document' => $document->load('currentVersion'),
            'parsed' => $parsed,
            'summary' => $parsed !== null ? $this->eInvoices->summary($parsed) : null,
        ]);
    }
}
