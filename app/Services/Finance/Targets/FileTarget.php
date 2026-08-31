<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FileTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Finance\Targets;

use App\Enums\Finance\{TransferChannel, TransferTarget};
use App\Models\Finance\BillingTransfer;
use App\Models\{MaterialUsage, TimeEntry};
use App\Services\Finance\BillingPositionBuilder;
use App\Support\CsvExport;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\CSV\StringHelper;
use CommonToolkit\Helper\Data\StringHelper as TextHelper;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Dateibasierte Übergabe (Feature 045, Teil B) für die Ziele `datev` und
 * `file`: erzeugt ein ehrlich benanntes „Übergabepaket (CSV)" — ausdrücklich
 * KEIN DATEV-Format und keine DATEV-Kompatibilitätszusage (siehe Feature-Doku,
 * „Risiken"). Die DATEV-Desktop-API folgt als eigener Adapter.
 *
 * Ablage analog {@see \App\Services\Export\ExportRunner} (gleiche Disk,
 * Pfadschema exports/finance/{org}/{Y-m}/...); Download läuft Gate-geprüft
 * über FinanceTransferController::download().
 *
 * Aufbau: kommentierter Kopf (# Übergabepaket, Kunde, Kanal, Zeitraum, Hash),
 * CSV-Header, eine Zeile je Quelle (Snapshot-Werte der Items), Summenzeile.
 */
class FileTarget implements FacturationTarget {
    public const DISK = \App\Services\Export\ExportRunner::DISK;

    public const BASE_PATH = 'exports/finance';

    private const BOM = TextHelper::BOM_UTF8;

    public function __construct(
        private readonly BillingPositionBuilder $positions,
    ) {}

    public function supports(TransferTarget $target): bool {
        return $target === TransferTarget::Datev || $target === TransferTarget::File;
    }

    public function transfer(BillingTransfer $transfer): TargetResult {
        $transfer->loadMissing(['items', 'customer']);

        $lines = $transfer->channel === TransferChannel::Time
            ? $this->timeLines($transfer)
            : $this->materialLines($transfer);

        $content = self::BOM . implode("\r\n", array_merge($this->headComments($transfer), $lines)) . "\r\n";

        $relativePath = sprintf(
            '%s/%d/%s/uebergabe-%d-%s-%s.csv',
            self::BASE_PATH,
            (int) $transfer->organization_id,
            CarbonImmutable::now()->format('Y-m'),
            (int) $transfer->id,
            $transfer->channel->value,
            CarbonImmutable::now()->format('Ymd_His'),
        );

        $disk = Storage::disk(self::DISK);
        if (! $disk->put($relativePath, $content)) {
            throw new RuntimeException('Übergabepaket konnte nicht gespeichert werden: ' . $relativePath);
        }

        return new TargetResult(filePath: $relativePath);
    }

    /**
     * Kommentierter Dateikopf: ehrliche Kennzeichnung + Prüfbezug (Hash).
     *
     * @return list<string>
     */
    private function headComments(BillingTransfer $transfer): array {
        $lines = [
            '# ' . __('finance.csv.package_title'),
            '# ' . __('finance.field.customer') . ': ' . $transfer->customer->name,
            '# ' . __('finance.field.channel') . ': ' . $transfer->channel->label(),
            '# ' . __('finance.field.period') . ': ' . ($transfer->period_from?->toDateString() ?? '—') . ' – ' . ($transfer->period_to?->toDateString() ?? '—'),
            '# ' . __('finance.field.payload_hash') . ': ' . $transfer->payload_hash,
        ];

        // Rechnungstexte des Nachweises (MVP-491) als Kopfzeilen — im
        // CSV-Paket gibt es keine Beleg-Textfelder.
        foreach (['intro_text' => 'finance.field.intro_text', 'closing_text' => 'finance.field.closing_text'] as $field => $label) {
            $text = trim((string) $transfer->{$field});
            if ($text !== '') {
                $lines[] = '# ' . __($label) . ': ' . TextHelper::normalizeWhitespace($text);
            }
        }

        return $lines;
    }

    /**
     * Zeit-Kanal: Datum, Mitarbeiter, Projekt/Auftrag, Tätigkeit, Stunden,
     * Satz, Betrag, Kommentar — eine Zeile je eingefrorener Position
     * (MVP-487), abschließend die Summenzeile.
     *
     * Damit zeigt das Paket exakt dieselben Positionen wie die Vorschau und die
     * API-Ziele: Taktung, Zusammenfassung, Preisfindung und Standardleistung
     * stecken im {@see BillingPositionBuilder}. Vorher stand hier eine Zeile je
     * Zeiteintrag mit den ungetakteten Item-Snapshots.
     *
     * @return list<string>
     */
    private function timeLines(BillingTransfer $transfer): array {
        $sourceIds = $transfer->items->where('source_type', TimeEntry::class)->pluck('source_id')->all();

        /** @var \Illuminate\Support\Collection<int, TimeEntry> $entriesById */
        $entriesById = TimeEntry::query()
            ->whereIn('id', $sourceIds)
            ->with('user:id,name')
            ->get()
            ->keyBy('id');

        $rows = [StringHelper::encodeLine([
            (string) __('finance.csv.date'),
            (string) __('finance.csv.employee'),
            (string) __('finance.csv.project'),
            (string) __('finance.csv.activity'),
            (string) __('finance.csv.hours'),
            (string) __('finance.csv.rate'),
            (string) __('finance.csv.amount'),
            (string) __('finance.csv.comment'),
        ], ';')];

        $totalHours = 0.0;
        $totalAmount = 0.0;

        foreach ($this->positions->positionsFor($transfer) as $position) {
            $hours = $position->quantityFloat();
            if ($hours <= 0) {
                continue;
            }

            // Ein Block kann Zeiten mehrerer Mitarbeiter bündeln.
            $employees = collect($position->source_ids ?? [])
                ->map(fn(int $id): ?TimeEntry => $entriesById->get($id))
                ->filter()
                ->map(fn(TimeEntry $e): string => (string) ($e->user->name ?? ''))
                ->filter()->unique()->implode(', ');

            // Formel-Guard (Sicherheitsscan 2026-08-23, S-46): Mitarbeitername,
            // Projektname und Beschreibung sind frei erfasst, und diese Datei
            // wird von Menschen in Excel geöffnet.
            $rows[] = StringHelper::encodeLine(CsvExport::guardRow([
                $position->service_from?->toDateString() ?? '',
                $employees,
                (string) $position->project?->name,
                (string) $position->kind,
                self::num($hours),
                self::num($position->unitPriceFloat()),
                self::num($position->amountFloat()),
                TextHelper::normalizeWhitespace((string) $position->description),
            ]), ';');

            $totalHours += $hours;
            $totalAmount += $position->amountFloat();
        }

        $rows[] = StringHelper::encodeLine([
            (string) __('finance.csv.total'), '', '', '',
            self::num($totalHours), '',
            self::num($totalAmount), '',
        ], ';');

        return $rows;
    }

    /**
     * Material-Kanal: Datum, Produkt, Menge, Einheit, Einzelpreis netto,
     * Betrag, Auftrag — eine Zeile je Materialverwendung + Summenzeile.
     *
     * @return list<string>
     */
    private function materialLines(BillingTransfer $transfer): array {
        $items = $transfer->items->where('source_type', MaterialUsage::class)->keyBy('source_id');

        $usages = MaterialUsage::query()
            ->whereIn('id', $items->keys()->all())
            ->with(['timesheet:id,work_date,project_id', 'timesheet.project:id,name'])
            ->get()
            ->sortBy(fn(MaterialUsage $u) => $u->timesheet?->work_date?->toDateString() ?? '')
            ->values();

        $rows = [StringHelper::encodeLine([
            (string) __('finance.csv.date'),
            (string) __('finance.csv.product'),
            (string) __('finance.csv.quantity'),
            (string) __('finance.csv.unit'),
            (string) __('finance.csv.unit_price_net'),
            (string) __('finance.csv.amount'),
            (string) __('finance.csv.project'),
        ], ';')];

        foreach ($usages as $usage) {
            $item = $items->get($usage->id);
            $rows[] = StringHelper::encodeLine(CsvExport::guardRow([
                $usage->timesheet?->work_date?->toDateString() ?? '',
                trim((string) $usage->description),
                self::num($item?->quantity),
                (string) $usage->unit,
                self::num($usage->unit_price?->toFloat()),
                self::num($item?->amount),
                $usage->timesheet->project->name ?? '',
            ]), ';');
        }

        $rows[] = StringHelper::encodeLine([
            (string) __('finance.csv.total'), '',
            self::num($transfer->total_quantity), '', '',
            self::num($transfer->total_amount), '',
        ], ';');

        return $rows;
    }

    /** Zahlen einheitlich mit 2 Nachkommastellen, Punkt als Dezimaltrenner. */
    private static function num(mixed $value): string {
        if ($value === null || $value === '') {
            return '';
        }

        return number_format((float) $value, 2, '.', '');
    }
}
