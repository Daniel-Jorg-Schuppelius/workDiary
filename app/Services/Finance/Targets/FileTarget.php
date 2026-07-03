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
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\CSV\StringHelper;
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

    private const BOM = \CommonToolkit\Helper\Data\StringHelper::BOM_UTF8;

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
        return [
            '# ' . __('finance.csv.package_title'),
            '# ' . __('finance.field.customer') . ': ' . $transfer->customer->name,
            '# ' . __('finance.field.channel') . ': ' . $transfer->channel->label(),
            '# ' . __('finance.field.period') . ': ' . ($transfer->period_from?->toDateString() ?? '—') . ' – ' . ($transfer->period_to?->toDateString() ?? '—'),
            '# ' . __('finance.field.payload_hash') . ': ' . $transfer->payload_hash,
        ];
    }

    /**
     * Zeit-Kanal: Datum, Mitarbeiter, Projekt/Auftrag, Tätigkeit, Stunden,
     * Satz, Betrag, Kommentar — eine Zeile je Quelle (Item-Snapshots für
     * Stunden/Betrag), abschließend die Summenzeile.
     *
     * @return list<string>
     */
    private function timeLines(BillingTransfer $transfer): array {
        $items = $transfer->items->where('source_type', TimeEntry::class)->keyBy('source_id');

        $entries = TimeEntry::query()
            ->whereIn('id', $items->keys()->all())
            ->with(['user:id,name', 'project:id,name'])
            ->orderBy('date')
            ->get();

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

        foreach ($entries as $entry) {
            $item = $items->get($entry->id);
            $rows[] = StringHelper::encodeLine([
                $entry->date?->toDateString() ?? '',
                $entry->user->name ?? '',
                $entry->project->name ?? '',
                $entry->kind->label(),
                self::num($item?->quantity),
                self::num($entry->hourly_rate),
                self::num($item?->amount),
                trim((string) $entry->description),
            ], ';');
        }

        $rows[] = StringHelper::encodeLine([
            (string) __('finance.csv.total'), '', '', '',
            self::num($transfer->total_quantity), '',
            self::num($transfer->total_amount), '',
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
            $rows[] = StringHelper::encodeLine([
                $usage->timesheet?->work_date?->toDateString() ?? '',
                trim((string) $usage->description),
                self::num($item?->quantity),
                (string) $usage->unit,
                self::num($usage->unit_price),
                self::num($item?->amount),
                $usage->timesheet->project->name ?? '',
            ], ';');
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
