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

use App\Enums\Finance\DatevBatchStatus;
use App\Models\Finance\DatevBookingBatch;
use App\Models\{GobdExport, Invoice, Organization, User};
use App\Services\Finance\Gdpdu\{
    BookingBatchItemsSection,
    BookingBatchesSection,
    CashDailyClosingsSection,
    CashEntriesSection,
    CustomersSection,
    ExpensesSection,
    GdpduSection,
    IncomingEInvoicesSection,
    InvoiceItemsSection,
    InvoicesSection,
    LedgerAccountsSection,
    LedgerEntriesSection,
    LedgerEntryLinesSection,
    LedgerOpenItemsSection,
    LedgerPeriodsSection,
    PaymentAllocationsSection,
    TimeEntriesSection,
};
use Carbon\CarbonInterface;
use CommonToolkit\Builders\{CSVDocumentBuilder, XmlDocumentBuilder};
use CommonToolkit\Entities\CSV\DataLine;
use CommonToolkit\Entities\XML\Element;
use CommonToolkit\Generators\CSV\CSVGenerator;
use CommonToolkit\Helper\Data\CryptoHelper;
use CommonToolkit\Helper\FileSystem\{File, Folder};
use CommonToolkit\Helper\FileSystem\FileTypes\ZipFile;

/**
 * GoBD-Datenträgerüberlassung Z3 (Feature 063, MVP-132): erzeugt ein
 * Prüfungspaket im GDPdU-Beschreibungsstandard — `index.xml` (Tabellen-/Feld-/
 * Formatbeschreibung) plus `;`-getrennte CSV-Datendateien, gebündelt als ZIP.
 * Toolkit-first: XML über `XmlDocumentBuilder`, CSV über `CSVDocumentBuilder`/
 * `CSVGenerator`, ZIP über `ZipFile`.
 *
 * Die Datenbereiche (Tabellenbeschreibung + Zeilen) liegen als
 * {@see GdpduSection}-Objekte unter `Gdpdu/` (Vollscan 2026-08-23, B13);
 * dieser Service orchestriert nur noch: Bereiche auflösen, CSVs schreiben,
 * `index.xml` bauen, ZIP + Hash. Deterministisch geordnet → derselbe Zeitraum
 * ergibt reproduzierbar denselben Paket-Hash. Der Paket-Hash geht über die
 * DATEIINHALTE (nicht das ZIP-Binär, das Zeitstempel enthält).
 * IDEA-Referenzverifikation bleibt extern (Bauturbo Welle C).
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

    /** @var array<string, GdpduSection>|null Schlüssel → Bereich (lazy). */
    private ?array $sections = null;

    /**
     * Verfügbare Datenbereiche — Reihenfolge = Auswahl-/Paketreihenfolge
     * (hash-relevant für die `index.xml`, daher nicht umsortieren).
     *
     * @return array<string, GdpduSection>
     */
    private function sections(): array {
        if ($this->sections !== null) {
            return $this->sections;
        }

        $map = [];
        foreach ([
            new InvoicesSection(),
            new InvoiceItemsSection(),
            new CustomersSection(),
            new TimeEntriesSection(),
            new BookingBatchesSection(),
            new BookingBatchItemsSection(),
            new PaymentAllocationsSection(),
            new CashEntriesSection(),
            new CashDailyClosingsSection(),
            new IncomingEInvoicesSection(),
            new LedgerAccountsSection(),
            new LedgerEntriesSection(),
            new LedgerEntryLinesSection(),
            new LedgerOpenItemsSection(),
            new LedgerPeriodsSection(),
            new ExpensesSection(),
        ] as $section) {
            $map[$section->key()] = $section;
        }

        return $this->sections = $map;
    }

    /** @return list<string> */
    public function availableSections(): array {
        return array_keys($this->sections());
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
        $counts = [];
        foreach ($this->sections() as $key => $section) {
            $counts[$key] = count($section->rows($organization, $from, $to));
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
        $map = $this->sections();
        $selected = array_values(array_filter($sections, static fn (string $s): bool => isset($map[$s])));
        if ($selected === []) {
            $selected = array_keys($map);
        }

        $encoding = isset(self::ENCODINGS[$encoding]) ? $encoding : self::ENCODING_CP1252;
        [$mbTarget, $dtdLabel] = self::ENCODINGS[$encoding];

        // Dateien im Speicher aufbauen: index.xml + je Bereich eine CSV.
        $files = ['index.xml' => $this->buildIndexXml($organization, $from, $to, $selected, $dtdLabel)];
        $recordCount = 0;
        foreach ($selected as $key) {
            $data = $map[$key]->rows($organization, $from, $to);
            $recordCount += count($data);
            $files[$map[$key]->definition()['file']] = $this->buildCsv($data, $mbTarget);
        }

        // Reproduzierbare Hashes über die DATEIINHALTE (nicht das ZIP-Binär).
        $fileHashes = [];
        foreach ($files as $name => $content) {
            $fileHashes[$name] = CryptoHelper::hash($content);
        }
        ksort($fileHashes);
        $packageHash = CryptoHelper::hash(implode("\n", array_map(
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
     * @param  list<string>  $selected
     */
    private function buildIndexXml(Organization $organization, CarbonInterface $from, CarbonInterface $to, array $selected, string $dtdEncoding): string {
        $map = $this->sections();

        $supplier = new Element('DataSupplier', null, null, null, [], [
            new Element('Name', $organization->name),
            new Element('Location', $organization->name),
            new Element('Comment', 'WorkDiary GoBD Z3 (GDPdU-Beschreibungsstandard)'),
        ]);

        $tables = [];
        foreach ($selected as $key) {
            $tables[] = $this->tableElement($map[$key]->definition(), $from, $to, $dtdEncoding);
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
        Folder::create($dir, 0700, true);
        $paths = [];
        try {
            foreach ($files as $name => $content) {
                $path = $dir . '/' . $name;
                File::write($path, $content);
                $paths[] = $path;
            }
            $zipPath = $dir . '/package.zip';
            ZipFile::create($paths, $zipPath);
            $binary = File::read($zipPath);
        } finally {
            foreach (glob($dir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }

        return $binary;
    }
}
