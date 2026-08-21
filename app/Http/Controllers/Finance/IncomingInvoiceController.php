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
use App\Services\Invoicing\EInvoice\IncomingEInvoiceService;
use CommonToolkit\Helper\Data\CryptoHelper;
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

        /** @var User $actor */
        $actor = Auth::user();

        // Zentrale Eingangsverarbeitung (MVP-165/167): Hash-Dedup, Parse,
        // Validierung, Vorschläge/Abweichungen, DMS-Ablage — kanalneutral.
        $result = $this->eInvoices->storeIncoming(
            $actor,
            $contents,
            $file->getMimeType(),
            $file->getRealPath(),
            'upload',
            $file,
        );

        $incoming = $result['incoming'];
        if ($result['status'] === 'duplicate' && $incoming !== null) {
            return redirect()->route('finance.incoming-invoices.show', $incoming->document_id)
                ->with('error', __('Diese E-Rechnung wurde bereits am :date erfasst (Dublette).', [
                    'date' => $incoming->received_at->isoFormat('L LT'),
                ]));
        }
        if ($result['status'] !== 'created' || $incoming === null || $result['document'] === null) {
            return back()->with('error', __('Die Datei ist keine lesbare E-Rechnung (XRechnung/ZUGFeRD).'));
        }

        return redirect()->route('finance.incoming-invoices.show', $result['document'])
            ->with('success', __('E-Rechnung :number erfasst und im DMS abgelegt.', [
                'number' => (string) data_get($incoming->summary, 'number'),
            ]));
    }

    /**
     * Download der extrahierten Rechnungs-XML (MVP-166, Restpaket):
     * deterministisch aus dem unveränderten Original extrahiert; der
     * Abruf wird als Übergabenachweis auditiert (MVP-168).
     */
    public function xml(Document $document): \Symfony\Component\HttpFoundation\Response {
        Gate::authorize('view', $document);
        abort_unless($document->document_type === DocumentType::Invoice, 404);

        $version = $document->currentVersion;
        abort_if($version === null || ! Storage::disk('local')->exists((string) $version->path), 404);

        $contents = (string) Storage::disk('local')->get((string) $version->path);
        $xml = $this->eInvoices->extractXml($contents, (string) $version->mime, Storage::disk('local')->path((string) $version->path));
        if ($xml === null) {
            return back()->with('error', __('Aus diesem Beleg lässt sich kein Rechnungs-XML extrahieren.'));
        }

        $filename = 'e-rechnung-' . $document->getKey() . '.xml';
        $document->audit('document.einvoice_xml_exported', [
            'filename' => $filename,
            'sha256' => CryptoHelper::hash($xml),
        ]);

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Idempotente Übergabe an die führende Buchhaltung (MVP-168): nur nach
     * fachlicher Freigabe; ein zweiter Aufruf ändert nichts (kein doppelter
     * Nachweis). Keine automatische Stammdaten- oder Belegänderung.
     */
    public function transfer(\App\Models\IncomingEInvoice $incoming): RedirectResponse {
        abort_unless(Auth::user()?->canManageBilling() ?? false, 403);

        if (! in_array($incoming->status, [\App\Models\IncomingEInvoice::STATUS_APPROVED, \App\Models\IncomingEInvoice::STATUS_PAYMENT_RELEASED], true)) {
            return back()->with('error', __('Nur fachlich freigegebene Eingänge werden an die Buchhaltung übergeben.'));
        }

        if ($incoming->transferred_at !== null) {
            return redirect()->route('finance.incoming-invoices.show', $incoming->document_id)
                ->with('success', __('Bereits am :date übergeben — kein erneuter Übergabevorgang.', [
                    'date' => $incoming->transferred_at->isoFormat('L LT'),
                ]));
        }

        $incoming->update(['transferred_at' => now(), 'transferred_by' => (int) Auth::id()]);
        $incoming->audit('incoming_einvoice.transferred', ['sha256' => $incoming->sha256]);

        return redirect()->route('finance.incoming-invoices.show', $incoming->document_id)
            ->with('success', __('Eingang an die führende Buchhaltung übergeben.'));
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

        $redirect = redirect()->route('finance.incoming-invoices.show', $incoming->document_id)
            ->with('success', __('Entscheidung gespeichert.'));

        // Feature 117: Bei der Zahlungsfreigabe warnen, wenn dem Lieferanten
        // Pflichtnachweise fehlen. Sperren wäre hier zu spät — die Leistung
        // ist erbracht —, aber schweigen wäre falsch: Genau die Altfälle,
        // deren Bestellung vor der Sperre entstand, laufen hier durch.
        $warning = $target === \App\Models\IncomingEInvoice::STATUS_PAYMENT_RELEASED
            ? $this->credentialWarning($incoming)
            : null;

        return $warning === null ? $redirect : $redirect->with('warning', $warning);
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

    /**
     * Warnung zu fehlenden Pflichtnachweisen des Lieferanten (Feature 117).
     * Der Lieferant wird über den Verkäufernamen des Belegs gefunden; ohne
     * Treffer gibt es nichts zu warnen — eine erfundene Zuordnung wäre
     * schlimmer als keine.
     */
    private function credentialWarning(\App\Models\IncomingEInvoice $incoming): ?string {
        $name = trim((string) ($incoming->seller_name ?? ''));
        if ($name === '') {
            return null;
        }

        $supplier = \App\Models\Supplier::query()
            ->where('organization_id', $incoming->organization_id)
            ->where('name', $name)
            ->first();
        if (! $supplier instanceof \App\Models\Supplier) {
            return null;
        }

        $missing = app(\App\Services\Supplier\SupplierCredentialService::class)->missingReasons($supplier);

        return $missing === [] ? null : (string) __('procurement.credentials.release_warning', [
            'supplier' => $supplier->name,
            'list' => implode(', ', $missing),
        ]);
    }
}
