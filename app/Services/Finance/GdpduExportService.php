<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GdpduExportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\Expense\ExpenseStatus;
use App\Enums\Finance\DatevBatchStatus;
use App\Models\{Customer, Expense, GobdExport, Invoice, InvoiceItem, Organization, TimeEntry, User};
use App\Models\Finance\{DatevBookingBatch, DatevBookingSource, PaymentAllocation};
use Carbon\CarbonInterface;
use CommonToolkit\Builders\{CSVDocumentBuilder, XmlDocumentBuilder};
use CommonToolkit\Entities\CSV\DataLine;
use CommonToolkit\Entities\XML\Element;
use CommonToolkit\Generators\CSV\CSVGenerator;
use CommonToolkit\Helper\FileSystem\FileTypes\ZipFile;
use Illuminate\Support\Carbon;

/**
 * GoBD-Datenträgerüberlassung Z3 (Feature 063, MVP-132): erzeugt ein
 * Prüfungspaket im GDPdU-Beschreibungsstandard — `index.xml` (Tabellen-/Feld-/
 * Formatbeschreibung) plus `;`-getrennte CSV-Datendateien, gebündelt als ZIP.
 * Toolkit-first: XML über `XmlDocumentBuilder`, CSV über `CSVDocumentBuilder`/
 * `CSVGenerator`, ZIP über `ZipFile`.
 *
 * Ausbaustufen: 1. Ausgangsrechnungen, Rechnungspositionen, Debitoren,
 * Zeitnachweise; 2. (A16, MVP-132) Buchungsstapel samt Positionen aus den
 * PERSISTIERTEN Exportnachweisen (DatevBookingBatch/-Source — Nachweis dessen,
 * was übergeben wurde, keine Neuberechnung), Zahlungszuordnungen inkl.
 * Chargeback-Kompensationen sowie freigegebene Spesen. Deterministisch
 * geordnet → derselbe Zeitraum ergibt reproduzierbar denselben Paket-Hash. Der
 * Paket-Hash geht über die DATEIINHALTE (nicht das ZIP-Binär, das Zeitstempel
 * enthält). IDEA-Referenzverifikation bleibt extern (Bauturbo Welle C).
 */
class GdpduExportService {
    private const CSV_DELIMITER = ';';

    private const CSV_ENCLOSURE = '"';

    /**
     * Zeichensatz der CSV-Datendateien. CP1252 („ANSI") ist prüferseitig der
     * sicherste Weg (€ auf 0x80), Default. ISO-8859-15 (€ auf 0xA4) als Variante,
     * UTF-8 für Werkzeuge, die es sicher lesen.
     */
    public const ENCODING_CP1252 = 'cp1252';

    public const ENCODING_ISO_8859_15 = 'iso-8859-15';

    public const ENCODING_UTF8 = 'utf-8';

    /** Auswahl → [mb-Zielcodierung, DTD-`<Encoding>`-Label]. */
    private const ENCODINGS = [
        self::ENCODING_CP1252 => ['Windows-1252', 'ANSI'],
        self::ENCODING_ISO_8859_15 => ['ISO-8859-15', 'ISO-8859-15'],
        self::ENCODING_UTF8 => ['UTF-8', 'UTF8'],
    ];

    /**
     * Verfügbare Datenbereiche mit Tabellen-/Spaltenbeschreibung.
     *
     * @return array<string, array{file: string, name: string, description: string, columns: list<array{name: string, type: string, accuracy?: int}>}>
     */
    private function sectionDefinitions(): array {
        return [
            'invoices' => [
                'file' => 'rechnungen.csv',
                'name' => 'Ausgangsrechnungen',
                'description' => 'Ausgangsrechnungen und Gutschriften des Prüfungszeitraums (nach Rechnungsdatum).',
                'columns' => [
                    ['name' => 'Rechnungsnummer', 'type' => 'alpha'],
                    ['name' => 'Typ', 'type' => 'alpha'],
                    ['name' => 'Status', 'type' => 'alpha'],
                    ['name' => 'Rechnungsdatum', 'type' => 'date'],
                    ['name' => 'Faelligkeit', 'type' => 'date'],
                    ['name' => 'Bezahlt_am', 'type' => 'date'],
                    ['name' => 'Kundennummer', 'type' => 'alpha'],
                    ['name' => 'Kunde', 'type' => 'alpha'],
                    ['name' => 'Waehrung', 'type' => 'alpha'],
                    ['name' => 'Netto', 'type' => 'numeric', 'accuracy' => 2],
                    ['name' => 'USt_Satz', 'type' => 'numeric', 'accuracy' => 2],
                    ['name' => 'USt_Betrag', 'type' => 'numeric', 'accuracy' => 2],
                    ['name' => 'Brutto', 'type' => 'numeric', 'accuracy' => 2],
                ],
            ],
            'invoice_items' => [
                'file' => 'rechnungspositionen.csv',
                'name' => 'Rechnungspositionen',
                'description' => 'Positionen der Ausgangsrechnungen des Prüfungszeitraums.',
                'columns' => [
                    ['name' => 'Rechnungsnummer', 'type' => 'alpha'],
                    ['name' => 'Position', 'type' => 'numeric', 'accuracy' => 0],
                    ['name' => 'Leistungsdatum', 'type' => 'date'],
                    ['name' => 'Beschreibung', 'type' => 'alpha'],
                    ['name' => 'Menge', 'type' => 'numeric', 'accuracy' => 2],
                    ['name' => 'Einheit', 'type' => 'alpha'],
                    ['name' => 'Einzelpreis', 'type' => 'numeric', 'accuracy' => 2],
                    ['name' => 'Betrag', 'type' => 'numeric', 'accuracy' => 2],
                ],
            ],
            'customers' => [
                'file' => 'debitoren.csv',
                'name' => 'Debitoren',
                'description' => 'Debitorenstammdaten der im Zeitraum berührten Kunden.',
                'columns' => [
                    ['name' => 'Kundennummer', 'type' => 'alpha'],
                    ['name' => 'Name', 'type' => 'alpha'],
                    ['name' => 'Firma', 'type' => 'alpha'],
                    ['name' => 'USt_IdNr', 'type' => 'alpha'],
                    ['name' => 'Steuernummer', 'type' => 'alpha'],
                    ['name' => 'Strasse', 'type' => 'alpha'],
                    ['name' => 'PLZ', 'type' => 'alpha'],
                    ['name' => 'Ort', 'type' => 'alpha'],
                    ['name' => 'Land', 'type' => 'alpha'],
                    ['name' => 'E-Mail', 'type' => 'alpha'],
                ],
            ],
            'time_entries' => [
                'file' => 'zeitnachweise.csv',
                'name' => 'Zeitnachweise',
                'description' => 'Erfasste Arbeitszeiten des Prüfungszeitraums (GoBD Rz. 20 nennt die Zeiterfassung ausdrücklich als vorlagepflichtig), nach Leistungsdatum.',
                'columns' => [
                    ['name' => 'Datum', 'type' => 'date'],
                    ['name' => 'Mitarbeiternummer', 'type' => 'alpha'],
                    ['name' => 'Mitarbeiter', 'type' => 'alpha'],
                    ['name' => 'Kunde', 'type' => 'alpha'],
                    ['name' => 'Projekt', 'type' => 'alpha'],
                    ['name' => 'Taetigkeit', 'type' => 'alpha'],
                    ['name' => 'Beschreibung', 'type' => 'alpha'],
                    ['name' => 'Dauer_Stunden', 'type' => 'numeric', 'accuracy' => 2],
                    ['name' => 'Abrechenbar', 'type' => 'alpha'],
                ],
            ],
            'booking_batches' => [
                'file' => 'buchungsstapel.csv',
                'name' => 'Buchungsstapel',
                'description' => 'Exportierte (festgeschriebene) DATEV-Buchungsstapel, deren Buchungszeitraum den Prüfungszeitraum berührt — persistierter Nachweisstand des Exports inkl. Datei-Hash.',
                'columns' => [
                    ['name' => 'Stapelnummer', 'type' => 'numeric', 'accuracy' => 0],
                    ['name' => 'Zeitraum_von', 'type' => 'date'],
                    ['name' => 'Zeitraum_bis', 'type' => 'date'],
                    ['name' => 'Exportiert_am', 'type' => 'alpha'],
                    ['name' => 'SKR', 'type' => 'alpha'],
                    ['name' => 'Auswahl', 'type' => 'alpha'],
                    ['name' => 'Buchungen', 'type' => 'numeric', 'accuracy' => 0],
                    ['name' => 'Gesamtbetrag', 'type' => 'numeric', 'accuracy' => 2],
                    ['name' => 'Export_Hash', 'type' => 'alpha'],
                ],
            ],
            'booking_batch_items' => [
                'file' => 'buchungsstapelpositionen.csv',
                'name' => 'Buchungsstapel-Positionen',
                'description' => 'Quellposten (Buchungssatz-Snapshots) der exportierten DATEV-Buchungsstapel inkl. Generalumkehr-/Storno-Kennzeichen — wie übergeben, keine Neuberechnung.',
                'columns' => [
                    ['name' => 'Stapelnummer', 'type' => 'numeric', 'accuracy' => 0],
                    ['name' => 'Belegfeld', 'type' => 'alpha'],
                    ['name' => 'Quelltyp', 'type' => 'alpha'],
                    ['name' => 'Konto', 'type' => 'alpha'],
                    ['name' => 'Gegenkonto', 'type' => 'alpha'],
                    ['name' => 'SH', 'type' => 'alpha'],
                    ['name' => 'Betrag', 'type' => 'numeric', 'accuracy' => 2],
                    ['name' => 'BU_Schluessel', 'type' => 'alpha'],
                    ['name' => 'Generalumkehr', 'type' => 'alpha'],
                ],
            ],
            'payment_allocations' => [
                'file' => 'zahlungszuordnungen.csv',
                'name' => 'Zahlungszuordnungen',
                'description' => 'Bestätigte Zahlungszuordnungen (inkl. Rückläufer-Kompensationen mit Grund) zu Bankumsätzen des Prüfungszeitraums, nach Buchungsdatum des Umsatzes.',
                'columns' => [
                    ['name' => 'Buchungsdatum', 'type' => 'date'],
                    ['name' => 'Bank_Betrag', 'type' => 'numeric', 'accuracy' => 2],
                    ['name' => 'Waehrung', 'type' => 'alpha'],
                    ['name' => 'Referenz', 'type' => 'alpha'],
                    ['name' => 'Art', 'type' => 'alpha'],
                    ['name' => 'Betrag', 'type' => 'numeric', 'accuracy' => 2],
                    ['name' => 'Belegtyp', 'type' => 'alpha'],
                    ['name' => 'Beleg', 'type' => 'alpha'],
                    ['name' => 'Zugeordnet_am', 'type' => 'alpha'],
                    ['name' => 'Grund', 'type' => 'alpha'],
                ],
            ],
            'expenses' => [
                'file' => 'spesen.csv',
                'name' => 'Spesen',
                'description' => 'Freigegebene Spesen/Auslagen des Prüfungszeitraums (nach Belegdatum); Entwürfe, offene und abgelehnte Belege sind nicht enthalten.',
                'columns' => [
                    ['name' => 'Belegnummer', 'type' => 'alpha'],
                    ['name' => 'Datum', 'type' => 'date'],
                    ['name' => 'Kategorie', 'type' => 'alpha'],
                    ['name' => 'Lieferant', 'type' => 'alpha'],
                    ['name' => 'Beschreibung', 'type' => 'alpha'],
                    ['name' => 'Waehrung', 'type' => 'alpha'],
                    ['name' => 'Netto', 'type' => 'numeric', 'accuracy' => 2],
                    ['name' => 'USt_Satz', 'type' => 'numeric', 'accuracy' => 2],
                    ['name' => 'USt_Betrag', 'type' => 'numeric', 'accuracy' => 2],
                    ['name' => 'Brutto', 'type' => 'numeric', 'accuracy' => 2],
                    ['name' => 'Status', 'type' => 'alpha'],
                    ['name' => 'Freigegeben_am', 'type' => 'alpha'],
                    ['name' => 'Freigegeben_von', 'type' => 'alpha'],
                    ['name' => 'Erstattet_am', 'type' => 'alpha'],
                ],
            ],
        ];
    }

    /** @return list<string> */
    public function availableSections(): array {
        return array_keys($this->sectionDefinitions());
    }

    /**
     * Wählbare CSV-Datendatei-Codierungen (Default `cp1252` zuerst).
     *
     * @return list<string>
     */
    public function availableEncodings(): array {
        return array_keys(self::ENCODINGS);
    }

    /**
     * Preflight: Datensatzzahlen je Bereich + Warnungen (z. B. Entwürfe im
     * Zeitraum, die steuerlich noch nicht festgeschrieben sind).
     *
     * @return array{counts: array<string, int>, warnings: list<string>}
     */
    public function preflight(Organization $organization, CarbonInterface $from, CarbonInterface $to): array {
        $rows = $this->collectRows($organization, $from, $to);
        $counts = [];
        foreach ($rows as $key => $data) {
            $counts[$key] = count($data);
        }

        $warnings = [];
        $drafts = Invoice::query()
            ->where('organization_id', $organization->id)
            ->whereBetween('issued_on', [$from->toDateString(), $to->toDateString()])
            ->where('status', 'draft')
            ->count();
        if ($drafts > 0) {
            $warnings[] = (string) __('gobd.preflight.drafts', ['count' => $drafts]);
        }
        if (($counts['invoices'] ?? 0) === 0) {
            $warnings[] = (string) __('gobd.preflight.empty_invoices');
        }

        // Entwurfs-Stapel im Zeitraum: zusammengestellt, aber noch nicht
        // festgeschrieben/exportiert — sie fehlen im Buchungsstapel-Nachweis.
        $draftBatches = DatevBookingBatch::query()
            ->where('organization_id', $organization->id)
            ->where('status', DatevBatchStatus::Draft->value)
            ->where('period_from', '<=', $to->toDateString())
            ->where('period_to', '>=', $from->toDateString())
            ->count();
        if ($draftBatches > 0) {
            $warnings[] = (string) __('gobd.preflight.draft_batches', ['count' => $draftBatches]);
        }

        return ['counts' => $counts, 'warnings' => $warnings];
    }

    /**
     * Erzeugt das Z3-Paket und legt den revisionssicheren Nachweis an.
     *
     * @param  list<string>  $sections  gewählte Bereiche (Teilmenge von availableSections)
     * @return array{filename: string, content: string, package_sha256: string, file_hashes: array<string, string>, record_count: int, export: GobdExport}
     */
    public function build(Organization $organization, CarbonInterface $from, CarbonInterface $to, array $sections, ?User $actor = null, string $encoding = self::ENCODING_CP1252): array {
        $defs = $this->sectionDefinitions();
        $selected = array_values(array_filter($sections, static fn (string $s): bool => isset($defs[$s])));
        if ($selected === []) {
            $selected = array_keys($defs);
        }

        $encoding = isset(self::ENCODINGS[$encoding]) ? $encoding : self::ENCODING_CP1252;
        [$mbTarget, $dtdLabel] = self::ENCODINGS[$encoding];

        $rows = $this->collectRows($organization, $from, $to);

        // Dateien im Speicher aufbauen: index.xml + je Bereich eine CSV.
        $files = ['index.xml' => $this->buildIndexXml($organization, $from, $to, $selected, $dtdLabel)];
        $recordCount = 0;
        foreach ($selected as $key) {
            $data = $rows[$key] ?? [];
            $recordCount += count($data);
            $files[$defs[$key]['file']] = $this->buildCsv($data, $mbTarget);
        }

        // Reproduzierbare Hashes über die DATEIINHALTE (nicht das ZIP-Binär).
        $fileHashes = [];
        foreach ($files as $name => $content) {
            $fileHashes[$name] = hash('sha256', $content);
        }
        ksort($fileHashes);
        $packageHash = hash('sha256', implode("\n", array_map(
            static fn (string $name, string $hash): string => $name . ':' . $hash,
            array_keys($fileHashes),
            array_values($fileHashes),
        )));

        $zip = $this->zip($files);

        $export = new GobdExport();
        $export->forceFill([
            'organization_id' => $organization->id,
            'period_from' => $from->toDateString(),
            'period_to' => $to->toDateString(),
            'sections' => $selected,
            'file_hashes' => $fileHashes,
            'package_sha256' => $packageHash,
            'record_count' => $recordCount,
            'created_by' => $actor?->id,
        ]);
        $export->save();
        $export->audit('gobd.exported', [
            'period' => $from->toDateString() . '..' . $to->toDateString(),
            'package_sha256' => $packageHash,
            'record_count' => $recordCount,
        ]);

        return [
            'filename' => 'gobd-z3-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.zip',
            'content' => $zip,
            'package_sha256' => $packageHash,
            'file_hashes' => $fileHashes,
            'record_count' => $recordCount,
            'export' => $export,
        ];
    }

    /**
     * Sammelt die Zeilen je Bereich (deterministisch geordnet). Debitoren =
     * genau die im Zeitraum per Rechnung berührten Kunden (Datenminimierung).
     *
     * @return array<string, list<list<string>>>
     */
    private function collectRows(Organization $organization, CarbonInterface $from, CarbonInterface $to): array {
        $invoices = Invoice::query()
            ->where('organization_id', $organization->id)
            ->whereBetween('issued_on', [$from->toDateString(), $to->toDateString()])
            ->with('customer:id,number,name,company,vat_id,tax_number,address_street,address_zip,address_city,country,email')
            ->orderBy('id')
            ->get();

        $invoiceRows = [];
        foreach ($invoices as $inv) {
            $invoiceRows[] = [
                $this->str($inv->number),
                $this->str($inv->type),
                $this->str($inv->status),
                $this->date($inv->issued_on),
                $this->date($inv->due_on),
                $this->date($inv->paid_on),
                $this->str($inv->customer->number),
                $this->str($inv->customer->name),
                $this->str($inv->currency->value),
                $this->num($inv->subtotal, 2),
                $this->num($inv->tax_rate, 2),
                $this->num($inv->tax_amount, 2),
                $this->num($inv->total, 2),
            ];
        }

        $numberById = $invoices->pluck('number', 'id');
        $itemRows = [];
        InvoiceItem::query()
            ->whereIn('invoice_id', $invoices->modelKeys())
            ->orderBy('invoice_id')->orderBy('position')
            ->get()
            ->each(function (InvoiceItem $item) use (&$itemRows, $numberById): void {
                $itemRows[] = [
                    $this->str($numberById[$item->invoice_id] ?? null),
                    $this->num($item->position, 0),
                    $this->date($item->service_date),
                    $this->str($item->description),
                    $this->num($item->quantity, 2),
                    $this->str($item->unit),
                    $this->num($item->unit_price, 2),
                    $this->num($item->amount, 2),
                ];
            });

        $customerRows = [];
        Customer::query()
            ->whereIn('id', $invoices->pluck('customer_id')->filter()->unique()->all())
            ->orderBy('number')
            ->get()
            ->each(function (Customer $c) use (&$customerRows): void {
                $customerRows[] = [
                    $this->str($c->number),
                    $this->str($c->name),
                    $this->str($c->company),
                    $this->str($c->vat_id),
                    $this->str($c->tax_number),
                    $this->str($c->address_street),
                    $this->str($c->address_zip),
                    $this->str($c->address_city),
                    $this->str($c->country),
                    $this->str($c->email),
                ];
            });

        $timeRows = [];
        TimeEntry::query()
            ->where('organization_id', $organization->id)
            ->whereNotNull('date')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->with(['user:id,name,personnel_number', 'project:id,name,customer_id', 'project.customer:id,name'])
            ->orderBy('date')->orderBy('id')
            ->get()
            ->each(function (TimeEntry $entry) use (&$timeRows): void {
                $personnelNo = $entry->user?->personnel_number;
                if ($personnelNo === null || $personnelNo === '') {
                    $personnelNo = (string) ($entry->user_id ?? '');
                }
                $timeRows[] = [
                    $this->date($entry->date),
                    $this->str($personnelNo),
                    $this->str($entry->user?->name),
                    $this->str($entry->project?->customer?->name),
                    $this->str($entry->project?->name),
                    $this->str($entry->activity_type->value),
                    $this->str($entry->description),
                    $this->num($entry->minutes / 60, 2),
                    $entry->billable ? 'Ja' : 'Nein',
                ];
            });

        [$batchRows, $batchItemRows] = $this->collectBookingBatchRows($organization, $from, $to);

        return [
            'invoices' => $invoiceRows,
            'invoice_items' => $itemRows,
            'customers' => $customerRows,
            'time_entries' => $timeRows,
            'booking_batches' => $batchRows,
            'booking_batch_items' => $batchItemRows,
            'payment_allocations' => $this->collectPaymentAllocationRows($organization, $from, $to),
            'expenses' => $this->collectExpenseRows($organization, $from, $to),
        ];
    }

    /**
     * Buchungsstapel-Nachweis (A16): NUR exportierte (festgeschriebene) Stapel,
     * deren Buchungszeitraum den Prüfungszeitraum berührt. Kopf + Positionen
     * kommen aus den persistierten Nachweisdaten (Batch/Source-Snapshots) —
     * bewusst KEINE Neuberechnung aus den Quellposten: exportiert ist, was im
     * Snapshot steht (inkl. Generalumkehr-/Storno-Kennzeichen, MVP-334).
     *
     * @return array{0: list<list<string>>, 1: list<list<string>>}
     */
    private function collectBookingBatchRows(Organization $organization, CarbonInterface $from, CarbonInterface $to): array {
        $batches = DatevBookingBatch::query()
            ->where('organization_id', $organization->id)
            ->where('status', DatevBatchStatus::Exported->value)
            ->where('period_from', '<=', $to->toDateString())
            ->where('period_to', '>=', $from->toDateString())
            ->orderBy('batch_no')->orderBy('id')
            ->get();

        $batchRows = [];
        foreach ($batches as $batch) {
            $batchRows[] = [
                $this->num($batch->batch_no, 0),
                $this->date($batch->period_from),
                $this->date($batch->period_to),
                $this->dateTime($batch->finalized_at),
                $this->str($batch->skr),
                $this->str($batch->selection_mode),
                $this->num($batch->booking_count, 0),
                $this->num($batch->total_amount, 2),
                $this->str($batch->file_hash),
            ];
        }

        $numberById = $batches->pluck('batch_no', 'id');
        $itemRows = [];
        DatevBookingSource::query()
            ->whereIn('datev_booking_batch_id', $batches->modelKeys())
            ->orderBy('datev_booking_batch_id')->orderBy('id')
            ->get()
            ->each(function (DatevBookingSource $source) use (&$itemRows, $numberById): void {
                $itemRows[] = [
                    $this->num($numberById[$source->datev_booking_batch_id] ?? null, 0),
                    $this->str($source->document_ref),
                    class_basename($source->source_type),
                    $this->str($source->debtor_account),
                    $this->str($source->revenue_account),
                    $this->str($source->soll_haben),
                    $this->num($source->amount, 2),
                    $this->str($source->tax_key),
                    $source->is_reversal ? 'Ja' : 'Nein',
                ];
            });

        return [$batchRows, $itemRows];
    }

    /**
     * Zahlungszuordnungen (A16): bestätigte, aktive Zuordnungen zu Bankumsätzen
     * mit Buchungsdatum im Prüfungszeitraum — inkl. Chargeback-Kompensationen
     * (negativer Betrag, Grund im `note`-Feld: `RET#<id> <Grund>`, MVP-334).
     * Aufgehobene Zuordnungen (unmatch = SoftDelete) sind kein Bestand mehr.
     *
     * @return list<list<string>>
     */
    private function collectPaymentAllocationRows(Organization $organization, CarbonInterface $from, CarbonInterface $to): array {
        $rows = [];
        PaymentAllocation::query()
            ->where('payment_allocations.organization_id', $organization->id)
            ->whereHas('transaction', fn ($q) => $q->whereBetween('booking_date', [$from->toDateString(), $to->toDateString()]))
            ->with(['transaction', 'allocatable'])
            ->orderBy('id')
            ->get()
            ->each(function (PaymentAllocation $allocation) use (&$rows): void {
                $tx = $allocation->transaction;
                $allocatable = $allocation->allocatable;
                $document = match (true) {
                    $allocatable instanceof Invoice => $this->str($allocatable->number),
                    $allocatable instanceof Expense => 'E-' . $allocatable->id,
                    default => '',
                };
                $rows[] = [
                    $this->date($tx?->booking_date),
                    $this->num($tx?->signedAmount(), 2),
                    $this->str($tx?->currency->value),
                    $this->str($tx?->end_to_end_id),
                    $this->str($allocation->kind->value),
                    $this->num($allocation->amount, 2),
                    $allocatable !== null ? class_basename($allocatable) : '',
                    $document,
                    $this->dateTime($allocation->confirmed_at),
                    $this->str($allocation->note),
                ];
            });

        return $rows;
    }

    /**
     * Freigegebene Spesen (A16): nur Belege, deren Freigabe erteilt wurde
     * (approved/reimbursed/invoiced) — Entwürfe, offene und abgelehnte Belege
     * sind steuerlich kein Aufwand und bleiben außen vor. Freigabe-Person als
     * ID (Datenminimierung), Zahlungsstatus über `Erstattet_am`.
     *
     * @return list<list<string>>
     */
    private function collectExpenseRows(Organization $organization, CarbonInterface $from, CarbonInterface $to): array {
        $rows = [];
        Expense::query()
            ->where('organization_id', $organization->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('status', [ExpenseStatus::Approved->value, ExpenseStatus::Reimbursed->value, ExpenseStatus::Invoiced->value])
            ->with('category:id,label')
            ->orderBy('date')->orderBy('id')
            ->get()
            ->each(function (Expense $expense) use (&$rows): void {
                $rows[] = [
                    'E-' . $expense->id,
                    $this->date($expense->date),
                    $this->str($expense->category?->label),
                    $this->str($expense->vendor),
                    $this->str($expense->description),
                    $this->str($expense->currency->value),
                    $this->num($expense->amount_net, 2),
                    $this->num($expense->tax_rate, 2),
                    $this->num($expense->tax_amount, 2),
                    $this->num($expense->amount_gross, 2),
                    $this->str($expense->status->value),
                    $this->dateTime($expense->decided_at),
                    $this->str($expense->decided_by),
                    $this->dateTime($expense->reimbursed_at),
                ];
            });

        return $rows;
    }

    /**
     * @param  list<string>  $selected
     */
    private function buildIndexXml(Organization $organization, CarbonInterface $from, CarbonInterface $to, array $selected, string $dtdEncoding): string {
        $defs = $this->sectionDefinitions();

        $supplier = new Element('DataSupplier', null, null, null, [], [
            new Element('Name', $organization->name),
            new Element('Location', $organization->name),
            new Element('Comment', 'WorkDiary GoBD Z3 (GDPdU-Beschreibungsstandard)'),
        ]);

        $tables = [];
        foreach ($selected as $key) {
            $tables[] = $this->tableElement($defs[$key], $from, $to, $dtdEncoding);
        }
        $media = new Element('Media', null, null, null, [], array_merge(
            [new Element('Name', 'Prüfungszeitraum ' . $from->toDateString() . ' bis ' . $to->toDateString())],
            $tables,
        ));

        $xml = XmlDocumentBuilder::create('DataSet')
            ->withEncoding('UTF-8')
            ->withFormatOutput(true)
            ->addChild('Version', '1.0')
            ->addElement($supplier)
            ->addElement($media)
            ->toString();

        // GDPdU-Beschreibungsstandard verlangt die DTD-Referenz; nach dem
        // XML-Prolog einfügen (der Builder erzeugt sie nicht selbst).
        return preg_replace(
            '/(<\?xml[^>]*\?>\R?)/',
            "$1<!DOCTYPE DataSet SYSTEM \"gdpdu-01-09-2004.dtd\">\n",
            $xml,
            1,
        ) ?? $xml;
    }

    /**
     * @param  array{file: string, name: string, description: string, columns: list<array{name: string, type: string, accuracy?: int}>}  $def
     */
    private function tableElement(array $def, CarbonInterface $from, CarbonInterface $to, string $dtdEncoding): Element {
        $columns = [];
        foreach ($def['columns'] as $col) {
            $columns[] = $this->columnElement($col);
        }

        $variableLength = new Element('VariableLength', null, null, null, [], array_merge([
            new Element('ColumnDelimiter', self::CSV_DELIMITER),
            new Element('RecordDelimiter', "\r\n"),
            new Element('TextEncapsulator', self::CSV_ENCLOSURE),
        ], $columns));

        $validity = new Element('Validity', null, null, null, [], [
            new Element('Range', null, null, null, [], [
                new Element('From', $from->toDateString()),
                new Element('To', $to->toDateString()),
            ]),
            new Element('Format', 'YYYY-MM-DD'),
        ]);

        return new Element('Table', null, null, null, [], [
            new Element('URL', null, null, null, [], [new Element('File', $def['file'])]),
            new Element('Name', $def['name']),
            new Element('Description', $def['description']),
            $validity,
            // DTD-Reihenfolge: Encoding vor (DecimalSymbol, DigitGroupingSymbol).
            new Element('Encoding', $dtdEncoding),
            new Element('DecimalSymbol', ','),
            new Element('DigitGroupingSymbol', '.'),
            $variableLength,
        ]);
    }

    /**
     * @param  array{name: string, type: string, accuracy?: int}  $col
     */
    private function columnElement(array $col): Element {
        $typeChild = match ($col['type']) {
            'numeric' => new Element('Numeric', null, null, null, [], [
                new Element('Accuracy', (string) ($col['accuracy'] ?? 2)),
            ]),
            'date' => new Element('Date', null, null, null, [], [new Element('Format', 'YYYY-MM-DD')]),
            default => new Element('AlphaNumeric'),
        };

        return new Element('VariableColumn', null, null, null, [], [
            new Element('Name', $col['name']),
            $typeChild,
        ]);
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function buildCsv(array $rows, string $mbTarget): string {
        $builder = new CSVDocumentBuilder(self::CSV_DELIMITER, self::CSV_ENCLOSURE);
        foreach ($rows as $row) {
            $builder->addRow(new DataLine($row, self::CSV_DELIMITER, self::CSV_ENCLOSURE));
        }

        $csv = (new CSVGenerator())->generate($builder->build(), includeHeader: false);

        // Der Generator liefert UTF-8; in die gewählte Datendatei-Codierung wandeln.
        return $mbTarget === 'UTF-8' ? $csv : (string) mb_convert_encoding($csv, $mbTarget, 'UTF-8');
    }

    /**
     * Bündelt die In-Memory-Dateien als ZIP (Toolkit-`ZipFile` über temporäre
     * Dateien; danach aufgeräumt).
     *
     * @param  array<string, string>  $files
     */
    private function zip(array $files): string {
        $dir = storage_path('app/gobd-tmp/' . bin2hex(random_bytes(8)));
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $paths = [];
        try {
            foreach ($files as $name => $content) {
                $path = $dir . '/' . $name;
                file_put_contents($path, $content);
                $paths[] = $path;
            }
            $zipPath = $dir . '/package.zip';
            ZipFile::create($paths, $zipPath);
            $binary = (string) file_get_contents($zipPath);
        } finally {
            foreach (glob($dir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }

        return $binary;
    }

    private function str(mixed $value): string {
        return trim((string) ($value ?? ''));
    }

    private function num(mixed $value, int $accuracy): string {
        if ($value === null || $value === '') {
            return '';
        }

        return number_format((float) $value, $accuracy, ',', '');
    }

    /** Zeitpunkt (Datum + Uhrzeit) als Alpha-Spalte — GDPdU-`Date` kennt nur Datum. */
    private function dateTime(mixed $value): string {
        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value)->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                return '';
            }
        }

        return '';
    }

    private function date(mixed $value): string {
        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d');
        }
        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value)->format('Y-m-d');
            } catch (\Throwable) {
                return '';
            }
        }

        return '';
    }
}
