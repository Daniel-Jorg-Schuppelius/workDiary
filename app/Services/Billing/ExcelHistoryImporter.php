<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExcelHistoryImporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Billing;

use App\Enums\Activity\ActivityCategoryType;
use App\Enums\Billing\AccountPaymentSource;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\ActivityCategory;
use App\Models\Billing\CustomerBillingAgreement;
use App\Models\{Customer, TimeEntry, User};
use Carbon\CarbonImmutable;
use CommonToolkit\Parsers\XLSXDocumentParser;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Einmal-Import der Excel-Zeiterfassung (Feature 098): Monatsblätter
 * („Januar 2025" …) → TimeEntries auf dem Standardprojekt des Kunden,
 * Abrechnungsblock „Abgerechnet" → Kundenkonto-Zahlung, „Vormonat" des
 * ältesten Blatts → Anfangssaldo. Idempotent (Wiederholungslauf gefahrlos);
 * Sätze entstehen über den RateCalculator-Hook, das Agreement muss daher
 * inkl. Satzzeilen VOR dem Import gepflegt sein.
 *
 * Blattlayout (0-basiert, Header in Zeile 1): A=Grund, B=Datum, C=Startzeit,
 * D=Endzeit; Abrechnungsblock L/M ab Zeile 2: Gesamt/Abgerechnet/Vormonat/Offen.
 */
class ExcelHistoryImporter {
    private const MONTHS = [
        'Januar' => 1, 'Februar' => 2, 'März' => 3, 'April' => 4,
        'Mai' => 5, 'Juni' => 6, 'Juli' => 7, 'August' => 8,
        'September' => 9, 'Oktober' => 10, 'November' => 11, 'Dezember' => 12,
    ];

    /**
     * @return list<array{sheet: string, year: int, month: int, entries_created: int, entries_skipped: int, minutes: int, payment: float|null, payment_created: bool, excel_gross: float|null}>
     */
    public function import(Customer $customer, string $file, User $user, string $timezone = 'Europe/Berlin'): array {
        $agreement = $customer->billingAgreement()->with('rates')->first();
        if ($agreement === null || $agreement->rates->isEmpty()) {
            throw new RuntimeException(
                'Kein Abrechnungsprofil mit Satzzeilen für diesen Kunden — bitte zuerst an der Kundenakte anlegen, sonst fehlen die Konditions-Sätze am Import.'
            );
        }

        $project = $customer->defaultProjectOrCreate();
        $document = XLSXDocumentParser::fromFile($file, hasHeader: true);

        $summary = [];
        $sheets = [];
        foreach ($document->getSheets() as $sheet) {
            $period = $this->parseSheetName($sheet->getName());
            if ($period !== null) {
                $sheets[] = ['sheet' => $sheet, 'year' => $period[0], 'month' => $period[1]];
            }
        }
        usort($sheets, static fn (array $a, array $b): int => [$a['year'], $a['month']] <=> [$b['year'], $b['month']]);

        $this->seedOpeningBalance($agreement, $sheets, $timezone);

        foreach ($sheets as $info) {
            $summary[] = $this->importSheet($agreement, $project->id, $user, $info, $timezone);
        }

        app(CustomerAccountStatementService::class)->recalculateOpen($agreement);

        return $summary;
    }

    /** @return array{0: int, 1: int}|null [Jahr, Monat] */
    private function parseSheetName(string $name): ?array {
        if (! preg_match('/^(\p{L}+)\s+(\d{4})$/u', trim($name), $m)) {
            return null;
        }
        $month = self::MONTHS[$m[1]] ?? null;

        return $month === null ? null : [(int) $m[2], $month];
    }

    /**
     * Anfangssaldo aus dem „Vormonat"-Feld des ältesten Blatts übernehmen,
     * sofern am Agreement noch keiner gepflegt ist.
     *
     * @param list<array{sheet: \CommonToolkit\Entities\XLSX\Sheet, year: int, month: int}> $sheets
     */
    private function seedOpeningBalance(CustomerBillingAgreement $agreement, array $sheets, string $timezone): void {
        if ($sheets === [] || ! ($agreement->opening_balance?->isZero() ?? true) || $agreement->opening_balance_date !== null) {
            return;
        }

        $first = $sheets[0];
        $carry = $this->billingBlockValue($first['sheet'], 'Vormonat');
        if ($carry === null) {
            return;
        }

        $agreement->update([
            // Grenz-Konvertierung: Excel-Rohwert (float) → Money.
            'opening_balance' => Money::ofFloat($carry, $agreement->currency),
            'opening_balance_date' => $this->monthStart($first['year'], $first['month'], $timezone)
                ->subDay()->toDateString(),
        ]);
    }

    /**
     * @param array{sheet: \CommonToolkit\Entities\XLSX\Sheet, year: int, month: int} $info
     * @return array{sheet: string, year: int, month: int, entries_created: int, entries_skipped: int, minutes: int, payment: float|null, payment_created: bool, excel_gross: float|null}
     */
    private function importSheet(CustomerBillingAgreement $agreement, int $projectId, User $user, array $info, string $timezone): array {
        $sheet = $info['sheet'];
        $created = 0;
        $skipped = 0;
        $minutes = 0;

        foreach ($sheet->getRows() as $row) {
            $cells = $row->toArray();
            $reason = trim((string) ($cells[0] ?? ''));
            $date = $this->normalizeDate($cells[1] ?? null, $timezone);
            $start = $this->normalizeTime($cells[2] ?? null);
            $end = $this->normalizeTime($cells[3] ?? null);

            if ($reason === '' || $date === null || $start === null || $end === null) {
                continue;
            }

            $startAt = CarbonImmutable::parse($date->toDateString() . ' ' . $start, $timezone)->utc();
            $endAt = CarbonImmutable::parse($date->toDateString() . ' ' . $end, $timezone)->utc();
            if ($endAt->lessThanOrEqualTo($startAt)) {
                $endAt = $endAt->addDay(); // Mitternachtsübergang
            }

            $exists = TimeEntry::query()
                ->where('project_id', $projectId)
                ->where('started_at', $startAt->format('Y-m-d H:i:s'))
                ->where('ended_at', $endAt->format('Y-m-d H:i:s'))
                ->exists();
            if ($exists) {
                $skipped++;
                continue;
            }

            $entry = TimeEntry::create([
                'organization_id' => $agreement->organization_id,
                'project_id' => $projectId,
                'user_id' => $user->id,
                'activity_category_id' => $this->categoryFor($agreement, $reason)->id,
                'kind' => TimeEntryKind::Work->value,
                'billable' => true,
                'started_at' => $startAt,
                'ended_at' => $endAt,
                'description' => null,
            ]);
            $created++;
            $minutes += (int) $entry->minutes;
        }

        [$payment, $paymentCreated] = $this->importPayment($agreement, $sheet, $info['year'], $info['month'], $timezone);

        return [
            'sheet' => $sheet->getName(),
            'year' => $info['year'],
            'month' => $info['month'],
            'entries_created' => $created,
            'entries_skipped' => $skipped,
            'minutes' => $minutes,
            'payment' => $payment,
            'payment_created' => $paymentCreated,
            'excel_gross' => $this->billingBlockValue($sheet, 'Gesamt'),
        ];
    }

    /** @return array{0: float|null, 1: bool} [Betrag, neu angelegt] */
    private function importPayment(CustomerBillingAgreement $agreement, \CommonToolkit\Entities\XLSX\Sheet $sheet, int $year, int $month, string $timezone): array {
        $amount = $this->billingBlockValue($sheet, 'Abgerechnet');
        if ($amount === null || abs($amount) < 0.005) {
            return [null, false];
        }
        $amount = round($amount, 2);

        // Die Excel kennt kein Zahldatum — Konvention: Monatsultimo.
        $paidOn = $this->monthStart($year, $month, $timezone)->endOfMonth()->toDateString();

        $exists = $agreement->payments()
            ->whereDate('paid_on', $paidOn)
            ->where('amount', $amount)
            ->where('source', AccountPaymentSource::Import->value)
            ->exists();
        if ($exists) {
            return [$amount, false];
        }

        $agreement->payments()->create([
            'organization_id' => $agreement->organization_id,
            'paid_on' => $paidOn,
            'amount' => $amount,
            'currency' => $agreement->currency->value,
            'source' => AccountPaymentSource::Import,
            'note' => 'Excel-Import ' . $sheet->getName(),
        ]);

        return [$amount, true];
    }

    /** Wert aus dem Abrechnungsblock (Spalte L=Label, M=Wert). */
    private function billingBlockValue(\CommonToolkit\Entities\XLSX\Sheet $sheet, string $label): ?float {
        foreach ($sheet->getRows() as $row) {
            $cells = $row->toArray();
            if (trim((string) ($cells[11] ?? '')) === $label) {
                $value = $cells[12] ?? null;

                return is_numeric($value) ? (float) $value : null;
            }
        }

        return null;
    }

    private function categoryFor(CustomerBillingAgreement $agreement, string $label): ActivityCategory {
        /** @var ActivityCategory $category */
        $category = ActivityCategory::query()
            ->where('label', $label)
            ->when($agreement->organization_id !== null, fn ($q) => $q->where(
                fn ($qq) => $qq->where('organization_id', $agreement->organization_id)->orWhereNull('organization_id')
            ))
            ->orderByRaw('organization_id IS NULL') // org-eigene Kategorie vor Plattform-Default
            ->first() ?? ActivityCategory::create([
                'organization_id' => $agreement->organization_id,
                'key' => Str::slug($label),
                'label' => $label,
                'activity_type' => ActivityCategoryType::Other,
                'billable_default' => true,
                'counts_as_work' => true,
                'active' => true,
            ]);

        return $category;
    }

    private function normalizeDate(mixed $value, string $timezone): ?CarbonImmutable {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance(\Carbon\Carbon::instance($value))->setTimezone($timezone)->startOfDay();
        }
        if (is_int($value) || is_float($value)) {
            // Excel-Serialdatum (Epoche 1899-12-30).
            return CarbonImmutable::parse('1899-12-30', $timezone)->addDays((int) $value);
        }
        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }
        foreach (['Y-m-d', 'd.m.Y'] as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat('!' . $format, $s, $timezone);
            } catch (\Carbon\Exceptions\InvalidFormatException) {
                continue;
            }
            if ($parsed instanceof CarbonImmutable && $parsed->format($format) === $s) {
                return $parsed;
            }
        }

        return null;
    }

    /** Erster Tag eines Monats in der Import-Zeitzone (nie null, im Gegensatz zu Carbon::create). */
    private function monthStart(int $year, int $month, string $timezone): CarbonImmutable {
        return CarbonImmutable::parse(sprintf('%04d-%02d-01', $year, $month), $timezone)->startOfDay();
    }

    private function normalizeTime(mixed $value): ?string {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i:s');
        }
        if (is_int($value) || is_float($value)) {
            $seconds = (int) round((fmod((float) $value, 1.0)) * 86400);

            return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
        }
        $s = trim((string) $value);
        if (preg_match('/^(\d{1,2}):(\d{2})(:(\d{2}))?$/', $s, $m)) {
            return sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], (int) ($m[4] ?? 0));
        }

        return null;
    }
}
