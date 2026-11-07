<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupportingDocumentService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\EInvoice;

use App\Enums\Invoicing\SupportingDocumentKind;
use App\Models\Contracts\SupportingDocumentCarrier;
use App\Models\Invoicing\{Invoice, InvoiceSupportingDocument};
use App\Models\Platform\User;
use App\Services\Attachments\FileAttacher;
use App\Services\Invoicing\ActivityReportPdfRenderer;
use App\Services\Timesheet\PdfRenderer as TimesheetPdfRenderer;
use App\Support\{CsvExport, DocumentLocale};
use CommonToolkit\Helper\Data\CryptoHelper;
use CommonToolkit\Helper\FileSystem\{File, Folder};
use ERechnungToolkit\Entities\SupportingDocument;
use ERechnungToolkit\Enums\{AttachmentMimeCode, ERechnungProfile};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\{Collection, Str};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;
use PDFToolkit\Helper\{PDFMergeHelper, PDFSecurityHelper};

/**
 * Rechnungsbegründende Unterlagen einer Rechnung (Feature 196): Hochladen,
 * Leistungsnachweis erzeugen, Reihenfolge, Einfrieren bei der Ausstellung und
 * Übergabe an das php-erechnung-toolkit (BG-24).
 *
 * Dateinamen vergibt der Dienst selbst (`nachweis-<lfd>-<name>.<endung>`,
 * Kleinbuchstaben, ASCII): so sind sie je Rechnung eindeutig (BR-DE-22) und
 * frei von Pfad- und Sonderzeichen. Erlaubt sind nur die sechs MIME-Typen,
 * die jeder Empfänger annehmen muss.
 */
final class SupportingDocumentService {
    /** Strengste Plattformgrenze (Baden-Württemberg); Bund, Länder und Peppol erlauben 200. */
    public const MAX_DOCUMENTS = 100;

    private const STORAGE_FOLDER = 'invoice-supporting-documents';

    public function __construct(
        private readonly FileAttacher $attacher,
        private readonly ActivityReportPdfRenderer $activityReports,
        private readonly TimesheetPdfRenderer $timesheets,
        private readonly RecipientProfiles $profiles,
    ) {}

    /**
     * @throws ValidationException MIME-Typ nicht zulässig, PDF verschlüsselt, Beleg nicht mehr änderbar
     */
    public function addUpload(SupportingDocumentCarrier $carrier, UploadedFile $file, ?string $description, User $actor): InvoiceSupportingDocument {
        $this->assertEditable($carrier);

        $content = (string) $file->get();
        $mime = $this->allowedMime($content);
        if ($mime === null) {
            throw ValidationException::withMessages(['file' => __('invoicing.supporting_documents.error.mime')]);
        }
        if ($mime === AttachmentMimeCode::PDF && PDFSecurityHelper::isEncrypted((string) $file->getRealPath())) {
            throw ValidationException::withMessages(['file' => __('invoicing.supporting_documents.error.encrypted')]);
        }

        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        return $this->store($carrier, SupportingDocumentKind::Custom, $content, $mime, $name, $description, $actor);
    }

    /** Link auf eine extern abgelegte Unterlage (BT-124), etwa „Große Anlagen“ der OZG-RE. */
    public function addLink(SupportingDocumentCarrier $carrier, string $url, ?string $description, User $actor): InvoiceSupportingDocument {
        $this->assertEditable($carrier);
        $this->assertCapacity($carrier);
        $position = $this->nextPosition($carrier);

        return $carrier->supportingDocuments()->create([
            'organization_id' => $carrier->carrierOrganizationId(),
            'position' => $position,
            'kind' => SupportingDocumentKind::Custom,
            'reference' => $this->reference($carrier, $position),
            'description' => $description,
            'external_url' => $url,
            'is_embedded' => false,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    /**
     * Erzeugt Leistungsnachweis, optional die unterschriebenen Stundenzettel und
     * die Zeiten als CSV neu; frühere erzeugte Unterlagen weichen.
     *
     * @return Collection<int, InvoiceSupportingDocument> leer, wenn keine Position Zeiten trägt
     */
    public function generateActivityReport(SupportingDocumentCarrier $carrier, ?User $actor = null): Collection {
        $this->assertEditable($carrier);

        return DB::transaction(fn (): Collection => $this->rebuildGenerated($carrier, $actor));
    }

    public function remove(InvoiceSupportingDocument $document): void {
        $carrier = $document->carrier() ?? throw ValidationException::withMessages(['file' => __('invoicing.supporting_documents.error.not_draft')]);
        $this->assertEditable($carrier);
        if ($document->isFrozen()) {
            throw ValidationException::withMessages(['file' => __('invoicing.supporting_documents.error.frozen')]);
        }
        DB::transaction(fn () => $this->deleteDocument($document));
        $this->renumber($carrier);
    }

    /** Verschiebt die Unterlage um eine Stelle (-1 nach oben, +1 nach unten). */
    public function move(InvoiceSupportingDocument $document, int $direction): void {
        $carrier = $document->carrier() ?? throw ValidationException::withMessages(['file' => __('invoicing.supporting_documents.error.not_draft')]);
        $this->assertEditable($carrier);

        $documents = $carrier->supportingDocuments()->get()->values();
        $index = $documents->search(static fn (InvoiceSupportingDocument $d): bool => $d->is($document));
        $target = is_int($index) ? $index + ($direction < 0 ? -1 : 1) : null;
        if ($target === null || $target < 0 || $target >= $documents->count()) {
            return;
        }
        // Eingefrorene Unterlagen behalten Platz und Kennung (sie sind schon versandt).
        if ($document->isFrozen() || $documents->get($target)?->isFrozen() !== false) {
            return;
        }

        $order = $documents->all();
        [$order[$index], $order[$target]] = [$order[$target], $order[$index]];
        $this->renumber($carrier, collect($order));
    }

    /**
     * Ausstellung (D3): erzeugte Unterlagen mit der endgültigen Nummer neu
     * bauen, bei Behördenkunden den Nachweis automatisch beilegen, dann alles
     * einfrieren. Läuft unter der Ausstellungssperre nach dem Statuswechsel.
     */
    public function prepareForIssue(Invoice $invoice, ?User $actor = null): void {
        $invoice->loadMissing(['customer', 'organization']);
        $documents = $invoice->supportingDocuments()->get();
        $hasGenerated = $documents->contains(static fn (InvoiceSupportingDocument $d): bool => $d->kind->isGenerated() && ! $d->isFrozen());

        if ($hasGenerated || ($documents->isEmpty() && $this->profiles->attachesActivityReport($invoice->customer, $invoice->organization))) {
            $this->rebuildGenerated($invoice, $actor);
        }

        $this->freeze($invoice);
        $this->noteExternalIssuer($invoice);
    }

    /**
     * Programm, das die Nummer der Rechnung vergibt; null, wenn workDiary sie vergibt.
     * Kein angebundenes Programm nimmt heute Unterlagen an einer Ausgangsrechnung an
     * (API-Abgleich 2026-11-07); deshalb Vermerk und Hinweis statt Übertragung.
     */
    public function externalIssuer(Invoice $invoice): ?string {
        $authority = app(\App\Services\Numbering\NumberAuthority::class);
        $source = $authority->sourceFor((int) $invoice->organization_id, $invoice->numberScope());

        return $source === \App\Services\Numbering\NumberAuthority::SOURCE_LOCAL ? null : $source;
    }

    private function noteExternalIssuer(Invoice $invoice): void {
        $source = $this->externalIssuer($invoice);
        $documents = $invoice->supportingDocuments()->get();
        if ($source === null || $documents->isEmpty()) {
            return;
        }
        $label = app(\App\Services\Numbering\NumberAuthority::class)->sourceLabel($source);
        $note = (string) __('invoicing.supporting_documents.transfer.unsupported', ['program' => $label]);
        foreach ($documents as $document) {
            $document->forceFill(['transfer_note' => $note])->save();
        }

        \App\Models\Integration\IntegrationInboxItem::query()->withoutGlobalScopes()->updateOrCreate(
            ['organization_id' => $invoice->organization_id, 'plugin_id' => $source, 'dedupe_key' => 'supporting-documents:' . $invoice->getKey()],
            [
                'source' => $source,
                'target_type' => $invoice->getMorphClass(),
                'external_type' => 'invoice',
                'external_id' => (string) $invoice->getKey(),
                'case_type' => \App\Models\Integration\IntegrationInboxItem::CASE_CONFLICT,
                'status' => \App\Enums\Integration\IntegrationInboxStatus::Open,
                'referenceable_type' => $invoice->getMorphClass(),
                'referenceable_id' => $invoice->getKey(),
                'display_subtitle' => $invoice->documentLabel() . ' ' . $invoice->number,
                'remote_snapshot' => [
                    'reason' => \App\Models\Integration\IntegrationInboxItem::REASON_SUPPORTING_DOCUMENTS,
                    'resolution' => \App\Models\Integration\IntegrationInboxItem::RESOLUTION_ACKNOWLEDGE_ONLY,
                    'local' => ['description' => (string) __('invoicing.supporting_documents.transfer.inbox_hint', ['program' => $label, 'count' => $documents->count()])],
                ],
                'occurred_at' => now(),
            ],
        );
    }

    /** Kennungen auf die endgültige Nummer setzen, Hash prüfen, Zeitpunkt festhalten. */
    public function freeze(SupportingDocumentCarrier $carrier): void {
        foreach ($carrier->supportingDocuments()->with('attachment')->get() as $document) {
            if ($document->isFrozen()) {
                continue;
            }
            if ($document->is_embedded) {
                $content = $this->content($document);
                if ($content === null || CryptoHelper::hash($content) !== $document->sha256) {
                    throw new \RuntimeException((string) __('invoicing.supporting_documents.error.integrity', ['reference' => $document->reference]));
                }
            }
            $document->forceFill([
                'reference' => $this->reference($carrier, $document->position),
                'frozen_at' => now(),
            ])->save();
        }
    }

    /**
     * Unterlagen für die E-Rechnung in Ausgabereihenfolge.
     *
     * @return list<SupportingDocument>
     */
    public function forEInvoice(SupportingDocumentCarrier $carrier): array {
        $documents = [];
        foreach ($carrier->supportingDocuments()->with('attachment')->get() as $document) {
            $content = $document->is_embedded ? $this->content($document) : null;
            $documents[] = new SupportingDocument(
                $document->reference,
                $document->description,
                $document->external_url,
                $content,
                $content !== null ? $document->mime : null,
                $content !== null ? $document->filename : null,
            );
        }

        return $documents;
    }

    public function content(InvoiceSupportingDocument $document): ?string {
        $attachment = $document->attachment;
        if ($attachment === null) {
            return null;
        }
        $disk = Storage::disk($attachment->disk);

        return $disk->exists($attachment->path) ? $disk->get($attachment->path) : null;
    }

    /** Dateien der Unterlagen einer Rechnung entfernen, die gelöscht wird (Zeilen räumt der Fremdschlüssel ab). */
    public function purgeFiles(SupportingDocumentCarrier $carrier): void {
        foreach ($carrier->supportingDocuments()->with('attachments')->get() as $document) {
            foreach ($document->attachments as $attachment) {
                Storage::disk($attachment->disk)->delete($attachment->path);
                $attachment->delete();
            }
        }
    }

    /**
     * Zulässiger MIME-Typ am Inhalt erkannt; null, wenn der Inhalt keiner der sechs ist.
     */
    public function allowedMime(string $content): ?AttachmentMimeCode {
        if ($content === '') {
            return null;
        }
        $detected = File::mimeTypeFromContent($content);
        if ($detected === false) {
            return null;
        }
        foreach (AttachmentMimeCode::allowedFor(ERechnungProfile::XRECHNUNG) as $code) {
            if ($code->acceptsDetected($detected) && ! in_array($code, [AttachmentMimeCode::XLSX, AttachmentMimeCode::ODS], true)) {
                return $code;
            }
        }

        // Tabellen erkennt finfo oft nur als ZIP-Behälter: die Datei selbst entscheidet.
        if (in_array($detected, [AttachmentMimeCode::XLSX->value, AttachmentMimeCode::ODS->value], true)) {
            return AttachmentMimeCode::from($detected);
        }

        return null;
    }

    /**
     * Erzeugte Unterlagen (Nachweis, CSV, Stundenzettel) neu aufbauen; eingefrorene bleiben.
     * Ohne abgerechnete Zeiten bleibt alles, wie es ist.
     *
     * @return Collection<int, InvoiceSupportingDocument>
     */
    private function rebuildGenerated(SupportingDocumentCarrier $carrier, ?User $actor): Collection {
        $pdf = $this->activityReports->output($carrier);
        if ($pdf === null) {
            return collect();
        }

        $carrier->supportingDocuments()->get()
            ->filter(static fn (InvoiceSupportingDocument $d): bool => $d->kind->isGenerated() && ! $d->isFrozen())
            ->each(fn (InvoiceSupportingDocument $d) => $this->deleteDocument($d));
        $this->renumber($carrier);

        $created = collect([$this->store($carrier, SupportingDocumentKind::ActivityReport, $pdf, AttachmentMimeCode::PDF, $this->label('activity_report', $carrier), $this->label('activity_report_description', $carrier), $actor)]);
        if ((bool) $this->profiles->setting('einvoice.activity_report_csv', $carrier->carrierOrganization())) {
            $created->push($this->store($carrier, SupportingDocumentKind::ActivityReport, $this->activityCsv($carrier), AttachmentMimeCode::CSV, $this->label('activity_csv', $carrier), $this->label('activity_csv_description', $carrier), $actor));
        }
        if ((bool) $this->profiles->setting('einvoice.activity_report_include_signed_timesheets', $carrier->carrierOrganization())) {
            foreach ($this->signedTimesheetFiles($carrier) as [$content, $name]) {
                $created->push($this->store($carrier, SupportingDocumentKind::SignedTimesheets, $content, AttachmentMimeCode::PDF, $name, $this->label('signed_timesheets_description', $carrier), $actor));
            }
        }

        return $created;
    }

    private function store(
        SupportingDocumentCarrier $carrier,
        SupportingDocumentKind $kind,
        string $content,
        AttachmentMimeCode $mime,
        string $name,
        ?string $description,
        ?User $actor,
    ): InvoiceSupportingDocument {
        $this->assertCapacity($carrier);

        $position = $this->nextPosition($carrier);
        $filename = $this->filename($position, $name, $mime);

        $document = $carrier->supportingDocuments()->create([
            'organization_id' => $carrier->carrierOrganizationId(),
            'position' => $position,
            'kind' => $kind,
            'reference' => $this->reference($carrier, $position),
            'description' => $description,
            'filename' => $filename,
            'mime' => $mime->value,
            'size' => strlen($content),
            'sha256' => CryptoHelper::hash($content),
            'is_embedded' => true,
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
        ]);

        $attachment = $this->attacher->storeContent(
            $document,
            $content,
            $filename,
            $mime->value,
            $actor?->id,
            ['organization_id' => $carrier->carrierOrganizationId()],
            self::STORAGE_FOLDER,
        );
        $document->forceFill(['attachment_id' => $attachment->id])->save();

        return $document->setRelation('attachment', $attachment);
    }

    private function deleteDocument(InvoiceSupportingDocument $document): void {
        foreach ($document->attachments()->get() as $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
            $attachment->delete();
        }
        $document->delete();
    }

    /**
     * Positionen lückenlos ab 1 und Dateinamen danach ausrichten (der Name trägt die laufende Nummer).
     *
     * @param  Collection<int, InvoiceSupportingDocument>|null  $order
     */
    private function renumber(SupportingDocumentCarrier $carrier, ?Collection $order = null): void {
        $documents = ($order ?? $carrier->supportingDocuments()->get())->values();
        DB::transaction(function () use ($documents, $carrier): void {
            // Zwischenschritt mit Platzhaltern, damit der eindeutige Dateiname beim Tausch nicht kollidiert.
            foreach ($documents as $document) {
                if (! $document->isFrozen() && $document->filename !== null) {
                    $document->forceFill(['filename' => 'tmp-' . $document->id . '-' . $document->filename])->save();
                }
            }
            foreach ($documents as $index => $document) {
                if ($document->isFrozen()) {
                    continue;
                }
                $position = $index + 1;
                $filename = $document->filename === null ? null : $this->filename($position, (string) preg_replace('/^tmp-\d+-nachweis-\d+-/', '', pathinfo($document->filename, PATHINFO_FILENAME)), AttachmentMimeCode::tryFrom((string) $document->mime) ?? AttachmentMimeCode::PDF);
                $document->forceFill([
                    'position' => $position,
                    'reference' => $this->reference($carrier, $position),
                    'filename' => $filename,
                ])->save();
                $document->attachment?->forceFill(['original_name' => $filename ?? $document->attachment->original_name])->save();
            }
        });
    }

    private function nextPosition(SupportingDocumentCarrier $carrier): int {
        return ((int) $carrier->supportingDocuments()->max('position')) + 1;
    }

    private function filename(int $position, string $name, AttachmentMimeCode $mime): string {
        $slug = Str::limit(Str::slug($name), 60, '');

        return sprintf('nachweis-%d-%s.%s', $position, $slug !== '' ? $slug : 'unterlage', $mime->extension());
    }

    private function reference(SupportingDocumentCarrier $carrier, int $position): string {
        return Str::limit(sprintf('%s-A%d', $carrier->supportingReferenceNumber(), $position), 64, '');
    }

    private function label(string $key, SupportingDocumentCarrier $carrier): string {
        return (string) DocumentLocale::within($carrier->carrierCustomer(), $carrier->carrierOrganization(), static fn (): string => (string) __('invoicing.supporting_documents.generated.' . $key));
    }

    private function activityCsv(SupportingDocumentCarrier $carrier): string {
        $data = $this->activityReports->viewData($carrier);

        return (string) DocumentLocale::within($carrier->carrierCustomer(), $carrier->carrierOrganization(), static function () use ($data): string {
            $header = array_map(
                static fn (string $key): string => (string) __('invoicing.activity_report.csv.' . $key),
                ['position', 'date', 'person', 'from', 'until', 'minutes', 'activity', 'task'],
            );
            $rows = [];
            foreach ($data['groups'] as $group) {
                foreach ($group['rows'] as $row) {
                    $rows[] = [
                        (string) ($group['position'] ?? ''),
                        $row['date']?->toDateString() ?? '',
                        (string) ($row['person'] ?? ''),
                        $row['started_at']?->format('H:i') ?? '',
                        $row['ended_at']?->format('H:i') ?? '',
                        (string) $row['minutes'],
                        $row['description'],
                        (string) ($row['task'] ?? ''),
                    ];
                }
            }

            // Formel-Guard: Tätigkeitstexte sind frei erfasst und beim Empfänger in Excel geöffnet.
            return CsvExport::toString($header, $rows, ';');
        });
    }

    /**
     * Unterschriebene Stundenzettel als eine Datei; ohne pdfunite je Stundenzettel eine.
     *
     * @return list<array{0: string, 1: string}> Inhalt und Name
     */
    private function signedTimesheetFiles(SupportingDocumentCarrier $carrier): array {
        $sheets = $this->activityReports->signedTimesheets($carrier);
        if ($sheets->isEmpty()) {
            return [];
        }
        $pdfs = $sheets->map(fn ($sheet): string => $this->timesheets->render($sheet))->values()->all();
        $name = $this->label('signed_timesheets', $carrier);
        if (count($pdfs) === 1) {
            return [[$pdfs[0], $name]];
        }

        $work = Folder::createTemp('isd-', 0700);
        try {
            $paths = [];
            foreach ($pdfs as $index => $pdf) {
                $path = $work . '/' . ($index + 1) . '.pdf';
                File::write($path, $pdf);
                $paths[] = $path;
            }
            $merged = $work . '/merged.pdf';
            if (PDFMergeHelper::merge($paths, $merged)) {
                return [[File::read($merged), $name]];
            }
        } finally {
            Folder::delete($work, true);
        }

        return array_map(static fn (string $pdf, int $index): array => [$pdf, $name . '-' . ($index + 1)], $pdfs, array_keys($pdfs));
    }

    private function assertEditable(SupportingDocumentCarrier $carrier): void {
        $reason = $carrier->supportingDocumentsLockedReason();
        if ($reason !== null) {
            throw ValidationException::withMessages(['file' => $reason]);
        }
    }

    private function assertCapacity(SupportingDocumentCarrier $carrier): void {
        if ($carrier->supportingDocuments()->count() >= self::MAX_DOCUMENTS) {
            throw ValidationException::withMessages(['file' => __('invoicing.supporting_documents.error.too_many', ['max' => self::MAX_DOCUMENTS])]);
        }
    }
}
