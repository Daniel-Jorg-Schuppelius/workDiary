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

use App\Enums\Billing\DocumentDirection;
use App\Enums\Document\DocumentType;
use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceRecognition, IncomingInvoiceTransferStatus};
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\Accounting\FixedAsset;
use App\Models\Customer\Customer;
use App\Models\Document\Document;
use App\Models\Invoicing\{IncomingEInvoice, IncomingEInvoiceTransfer};
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Services\Invoicing\EInvoice\{IncomingEInvoiceService, IncomingInvoiceTransferGate, IncomingInvoiceTransferService};
use App\Support\{CarbonFmt, SortableQuery};
use CommonToolkit\Helper\Data\CryptoHelper;
use CommonToolkit\Helper\FileSystem\File;
use Illuminate\Http\{RedirectResponse, Request, UploadedFile};
use Illuminate\Support\Facades\{Auth, Gate, Storage};
use Illuminate\View\View;

/**
 * Eingangs-E-Rechnung (Nachtrag 045b): Empfang + Visualisierung von
 * XRechnung/ZUGFeRD. Die Rechnung wird als Document (Typ Rechnung) im DMS
 * abgelegt — KEINE lokale Invoice (Rechnungshoheit beim externen
 * Faktura-Programm); die Detailseite parst das Original bei jedem Aufruf.
 */
class IncomingInvoiceController extends Controller {
    use ResolvesGlobalDateRange;

    public function __construct(
        private readonly IncomingEInvoiceService $eInvoices,
        private readonly IncomingInvoiceTransferService $transfers,
    ) {}

    /** Reiter der Arbeitsliste (MVP-1110/1111): benannte Filterzustände. */
    public const TABS = ['assign', 'review', 'transfer', 'failed', 'all'];

    /** Sortierbare Spalten → SQL-Spalte. */
    private const SORTS = [
        'received_at' => 'received_at',
        'issue_date' => 'issue_date',
        'amount' => 'amount_gross',
        'number' => 'invoice_number',
    ];

    /**
     * Arbeitsliste des Rechnungseingangs (Feature 163, MVP-1110): Zuzuordnen
     * und Zu prüfen immer vollständig, „Alle“ im globalen Zeitraum. Sichtbar
     * ist, was das Original im DMS sehen lässt.
     */
    public function index(Request $request): View {
        Gate::authorize('viewAny', Document::class);
        $user = $this->authUser();
        $tab = in_array($request->query('tab'), self::TABS, true) ? (string) $request->query('tab') : 'assign';
        [$sort, $dir] = SortableQuery::resolve($request, self::SORTS, 'received_at');
        $direction = DocumentDirection::tryFrom($request->string('direction')->toString());
        $recognition = IncomingInvoiceRecognition::tryFrom($request->string('recognition')->toString());
        $term = trim($request->string('q')->toString());

        $base = static fn (): \Illuminate\Database\Eloquent\Builder => IncomingEInvoice::query()
            ->whereIn('document_id', Document::query()->visibleTo($user)->select('id'));
        $toAssign = static fn (\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder => $query
            ->whereNull('supplier_id')->whereNull('customer_id')
            ->where('status', '!=', IncomingEInvoiceStatus::Rejected->value);
        $toReview = static fn (\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder => $query
            ->where(static fn ($party) => $party->whereNotNull('supplier_id')->orWhereNotNull('customer_id'))
            ->whereIn('status', [IncomingEInvoiceStatus::Received->value, IncomingEInvoiceStatus::Question->value]);
        $inTransfer = static fn (\Illuminate\Database\Eloquent\Builder $query, array $states): \Illuminate\Database\Eloquent\Builder => $query
            ->whereHas('transfers', static fn ($journal) => $journal->whereIn('status', array_map(static fn (IncomingInvoiceTransferStatus $s): string => $s->value, $states)));
        $open = [IncomingInvoiceTransferStatus::Pending, IncomingInvoiceTransferStatus::Waiting];
        $failed = [IncomingInvoiceTransferStatus::Failed];
        [$from, $to] = $this->globalDateRangeBounds();

        $query = match ($tab) {
            'assign' => $toAssign($base()),
            'review' => $toReview($base()),
            'transfer' => $inTransfer($base(), $open),
            'failed' => $inTransfer($base(), $failed),
            default => $base()->whereBetween('received_at', [$from, $to]),
        };
        $query->with(['document', 'supplier:id,name,is_collective', 'customer:id,name,is_collective'])
            ->when(in_array($tab, ['transfer', 'failed'], true), static fn ($q) => $q->with('transfers'))
            ->when($direction !== null, static fn ($q) => $q->where('direction', $direction?->value))
            ->when($recognition !== null, static fn ($q) => $q->where('recognition', $recognition?->value))
            ->when($term !== '', static fn ($q) => $q->where(static fn ($w) => $w->whereLikeEscaped('invoice_number', $term)
                ->orWhereLikeEscaped('seller_name', $term)->orWhereLikeEscaped('buyer_name', $term)->orWhereLikeEscaped('sender_email', $term)));
        $query->orderBy(self::SORTS[$sort], $dir)->orderByDesc('id');

        return view('finance.incoming-invoices.index', [
            'incomings' => $query->paginate(25)->withQueryString(),
            'tab' => $tab,
            'sort' => $sort,
            'dir' => $dir,
            'assignCount' => $toAssign($base())->count(),
            'reviewCount' => $toReview($base())->count(),
            'transferCount' => $inTransfer($base(), $open)->count(),
            'failedCount' => $inTransfer($base(), $failed)->count(),
            'canUpload' => Gate::allows('create', Document::class),
            'canManage' => $user->canManageBilling(),
            'bulkParties' => $tab === 'assign' && $user->canManageBilling() ? $this->assignmentOptions() : null,
        ]);
    }

    /**
     * Auswahl für Zuordnungsdialog und Sammelaktion: Lieferanten und Kunden
     * ohne Sammelkontakte; die Sammelkontakte stehen als eigene Wahl davor.
     *
     * @return array{suppliers: \Illuminate\Support\Collection<int, Supplier>, customers: \Illuminate\Support\Collection<int, Customer>}
     */
    private function assignmentOptions(): array {
        return [
            'suppliers' => Supplier::query()->withoutCollective()->whereNull('archived_at')->orderBy('name')->get(['id', 'name', 'vat_id']),
            'customers' => Customer::query()->withoutCollective()->whereNull('archived_at')->orderBy('name')->get(['id', 'name', 'vat_id']),
        ];
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', Document::class);

        // MVP-1066: auch PDF ohne E-Rechnungsdaten und Fotos, mehrere auf einmal.
        $types = 'mimetypes:application/xml,text/xml,text/plain,application/pdf,image/jpeg,image/png,image/tiff';
        $request->validate([
            'files' => ['required_without:file', 'array', 'max:20'],
            'files.*' => ['file', 'max:20480', $types],
            'file' => ['required_without:files', 'file', 'max:20480', $types],
        ]);
        /** @var list<UploadedFile> $files */
        $files = array_values(array_filter((array) ($request->file('files') ?? [$request->file('file')]), static fn ($f): bool => $f instanceof UploadedFile));

        /** @var User $actor */
        $actor = Auth::user();
        // Zentrale Eingangsverarbeitung (MVP-165/167): Hash-Dedup, Parse,
        // Validierung, Vorschläge/Abweichungen, DMS-Ablage — kanalneutral.
        $results = array_map(fn (UploadedFile $file): array => $this->eInvoices->storeIncoming(
            $actor,
            File::read((string) $file->getRealPath()),
            $file->getMimeType(),
            $file->getRealPath(),
            'upload',
            $file,
        ), $files);

        if (count($results) > 1) {
            $count = static fn (string $status): int => count(array_filter($results, static fn (array $r): bool => $r['status'] === $status));

            return redirect()->toList('finance.incoming-invoices.index')->with('success', __(':created Rechnungen erfasst, :duplicates Dubletten, :failed nicht lesbar oder abgewiesen.', [
                'created' => $count('created'),
                'duplicates' => $count('duplicate'),
                'failed' => $count('unreadable') + $count('infected'),
            ]));
        }

        $result = $results[0];
        $incoming = $result['incoming'];
        if ($result['status'] === 'duplicate' && $incoming !== null) {
            return redirect()->route('finance.incoming-invoices.show', $incoming->document)
                ->with('error', __('Diese E-Rechnung wurde bereits am :date erfasst (Dublette).', [
                    'date' => CarbonFmt::orgTz($incoming->received_at)->isoFormat('L LT'),
                ]));
        }
        if ($result['status'] !== 'created' || $incoming === null || $result['document'] === null) {
            if ($result['status'] === 'infected') {
                return back()->with('error', __('Die Datei wurde von der Sicherheitsprüfung abgewiesen und nicht abgelegt.'));
            }

            return back()->with('error', __('Die Datei ist keine lesbare Rechnung (XRechnung, ZUGFeRD oder PDF/Bild mit erkennbarer Nummer und Summe).'));
        }

        return redirect()->route('finance.incoming-invoices.show', $result['document'])
            ->with('success', __((bool) data_get($incoming->summary, 'unstructured') ? 'Rechnung :number aus PDF bzw. Bild erkannt — bitte die Werte prüfen.' : 'E-Rechnung :number erfasst und im DMS abgelegt.', [
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
     * Übergabe an die Buchhaltung. Mit eingeschalteten Zielen (MVP-1111)
     * startet sie sofort für alle Ziele, auch nach ausgeschöpften
     * Wiederholungen; das Tor entscheidet. Ohne Ziel bleibt es beim
     * idempotenten Vermerk nach fachlicher Freigabe (MVP-168).
     */
    public function transfer(IncomingEInvoice $incoming): RedirectResponse {
        abort_unless(Auth::user()?->canManageBilling() ?? false, 403);
        $back = redirect()->route('finance.incoming-invoices.show', $incoming->document);

        $organization = $incoming->organization;
        if ($organization !== null && $this->transfers->hasTargets($organization)) {
            $journals = $this->transfers->transfer($incoming, $this->authUser());
            if ($journals === []) {
                return $back->with('info', __('Kein Buchhaltungsziel gilt für diesen Eingang, oder eine Übergabe läuft gerade.'));
            }
            $done = count(array_filter($journals, static fn (IncomingEInvoiceTransfer $journal): bool => $journal->status->isFinal()));

            return $done === count($journals)
                ? $back->with('success', __('Eingang an die Buchhaltung übergeben.'))
                : $back->with('warning', __('Übergabe: :done von :total Zielen abgeschlossen. Die Gründe stehen bei der Übergabe.', ['done' => $done, 'total' => count($journals)]));
        }

        if (! in_array($incoming->status, [IncomingEInvoiceStatus::Approved, IncomingEInvoiceStatus::PaymentReleased], true)) {
            return back()->with('error', __('Nur fachlich freigegebene Eingänge werden an die Buchhaltung übergeben.'));
        }

        if ($incoming->transferred_at !== null) {
            return $back->with('success', __('Bereits am :date übergeben — kein erneuter Übergabevorgang.', [
                'date' => CarbonFmt::orgTz($incoming->transferred_at)->isoFormat('L LT'),
            ]));
        }

        $incoming->update(['transferred_at' => now(), 'transferred_by' => (int) Auth::id()]);
        $incoming->audit('incoming_einvoice.transferred', ['sha256' => $incoming->sha256]);

        return $back->with('success', __('Eingang an die führende Buchhaltung übergeben.'));
    }

    /**
     * Prüf-Entscheidung (MVP-167): Freigabe, Ablehnung, Rückfrage sowie
     * Zahlungsfreigabe (nur NACH fachlicher Freigabe). Keine automatische
     * Stammdatenänderung — reine Statusführung mit Audit.
     */
    public function decide(Request $request, \App\Models\Invoicing\IncomingEInvoice $incoming): RedirectResponse {
        abort_unless(Auth::user()?->canManageBilling() ?? false, 403);

        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected,question,payment_released'],
            'note' => ['nullable', 'string', 'max:500', 'required_if:decision,rejected'],
        ]);

        $target = IncomingEInvoiceStatus::from((string) $data['decision']);
        // Ausgangsbelege aus dem Postfach (MVP-1107) bezahlt nicht die Organisation.
        if ($target === IncomingEInvoiceStatus::PaymentReleased && $incoming->direction === DocumentDirection::Outgoing) {
            return back()->with('error', __('Ausgangsbelege werden nicht zur Zahlung freigegeben.'));
        }
        // Eine Rückfrage darf mit neuer Anmerkung wiederholt werden.
        $repeatedQuestion = $target === IncomingEInvoiceStatus::Question && $incoming->status === $target;
        if (! $repeatedQuestion && ! $incoming->status->canTransitionTo($target)) {
            return back()->with('error', __('Übergang :from → :to ist nicht zulässig.', ['from' => $incoming->status->value, 'to' => $target->value]));
        }

        $incoming->update([
            'status' => $target,
            'decided_by' => (int) Auth::id(),
            'decided_at' => now(),
            'decision_note' => $data['note'] ?? null,
        ]);
        $incoming->audit('incoming_einvoice.decided', ['to' => $target->value]);

        $redirect = redirect()->route('finance.incoming-invoices.show', $incoming->document)
            ->with('success', __('Entscheidung gespeichert.'));

        // Feature 117: Bei der Zahlungsfreigabe warnen, wenn dem Lieferanten
        // Pflichtnachweise fehlen. Sperren wäre hier zu spät — die Leistung
        // ist erbracht —, aber schweigen wäre falsch: Genau die Altfälle,
        // deren Bestellung vor der Sperre entstand, laufen hier durch.
        $warning = $target === IncomingEInvoiceStatus::PaymentReleased
            ? $this->credentialWarning($incoming)
            : null;

        return $warning === null ? $redirect : $redirect->with('warning', $warning);
    }

    public function show(Document $document): View {
        Gate::authorize('view', $document);
        abort_unless($document->document_type === DocumentType::Invoice, 404);
        $incoming = \App\Models\Invoicing\IncomingEInvoice::query()->where('document_id', $document->id)->first();

        $version = $document->currentVersion;
        $parsed = null;
        if ($version !== null && Storage::disk('local')->exists((string) $version->path)) {
            $parsed = $this->eInvoices->parse(
                (string) Storage::disk('local')->get((string) $version->path),
                (string) $version->mime,
                Storage::disk('local')->path((string) $version->path),
            );
        }

        $organization = $incoming?->organization;
        $targets = $organization !== null ? $this->transfers->targets($organization) : [];

        return view('finance.incoming-invoices.show', [
            'incoming' => $incoming,
            'transferTargets' => $targets,
            'transferJournals' => $incoming !== null && $targets !== [] ? $incoming->transfers()->get()->keyBy('target') : collect(),
            'transferBlockers' => $incoming !== null && $targets !== [] ? app(IncomingInvoiceTransferGate::class)->blockers($incoming) : [],
            'document' => $document->load('currentVersion'),
            'parsed' => $parsed,
            'summary' => $parsed !== null ? $this->eInvoices->summary($parsed) : null,
            'fixedAsset' => $incoming === null ? null : FixedAsset::query()
                ->where('source_type', $incoming->getMorphClass())->where('source_id', $incoming->id)->first(),
        ]);
    }

    /**
     * Warnung zu fehlenden Pflichtnachweisen des Lieferanten (Feature 117).
     * Der Lieferant wird über den Verkäufernamen des Belegs gefunden; ohne
     * Treffer gibt es nichts zu warnen — eine erfundene Zuordnung wäre
     * schlimmer als keine.
     */
    private function credentialWarning(\App\Models\Invoicing\IncomingEInvoice $incoming): ?string {
        $name = trim((string) ($incoming->seller_name ?? ''));
        // Feste Zuordnung (MVP-1108) zuerst, der Name nur für Altbestand.
        $supplier = $incoming->supplier ?? ($name === '' ? null : \App\Models\Supplier\Supplier::query()
            ->where('organization_id', $incoming->organization_id)
            ->where('name', $name)
            ->first());
        if (! $supplier instanceof \App\Models\Supplier\Supplier) {
            return null;
        }

        $missing = app(\App\Services\Supplier\SupplierCredentialService::class)->missingReasons($supplier);

        return $missing === [] ? null : (string) __('procurement.credentials.release_warning', [
            'supplier' => $supplier->name,
            'list' => implode(', ', $missing),
        ]);
    }
}
