<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecapitulativeStatementService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Filing;

use App\Enums\Finance\{AccountingEntryStatus, VatFilingInterval};
use App\Models\Accounting\{AccountingEntryLine, AccountingTaxCode};
use App\Models\{Customer, Organization};
use Carbon\CarbonImmutable;

/**
 * Zusammenfassende Meldung (Feature 125, MVP-687).
 *
 * § 18a UStG: Meldezeitraum ist das Kalendervierteljahr, solange die
 * innergemeinschaftlichen Lieferungen im laufenden oder in einem der vier
 * vorangegangenen Quartale 50.000 € nicht übersteigen — sonst der Monat. Die
 * Frist ist der 25. Tag danach, und die **Dauerfristverlängerung gilt hier
 * nicht**.
 *
 * Erkannt werden die Umsätze über die Kennziffer 41 des Steuerkennzeichens
 * (MVP-688) — nicht über Kontonummern.
 */
class RecapitulativeStatementService {
    /** § 18a Abs. 1 S. 2 UStG. */
    public const QUARTERLY_THRESHOLD = 50000.0;

    /** Kennziffer der innergemeinschaftlichen Lieferungen in der UStVA. */
    public const FIELD = '41';

    private const POSTED = [AccountingEntryStatus::Posted->value, AccountingEntryStatus::Reversed->value];

    /**
     * Meldezeitraum eines Quartals: monatlich, sobald eine der letzten fünf
     * Quartalssummen über der Schwelle liegt.
     */
    public function intervalFor(Organization $organization, CarbonImmutable $date): VatFilingInterval {
        $quarterStart = CarbonImmutable::parse(sprintf('%04d-%02d-01', $date->year, ((int) ceil($date->month / 3) - 1) * 3 + 1));

        for ($back = 0; $back <= 4; $back++) {
            $from = $quarterStart->subMonths(3 * $back);
            $sum = (float) $this->totalFor($organization, $from, $from->addMonths(2)->endOfMonth());

            if ($sum > self::QUARTERLY_THRESHOLD) {
                return VatFilingInterval::Monthly;
            }
        }

        return VatFilingInterval::Quarterly;
    }

    /** Summe der innergemeinschaftlichen Lieferungen im Zeitraum. */
    public function totalFor(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): string {
        $total = '0.00';

        foreach ($this->lines($organization, $from, $to) as $line) {
            $amount = (float) ($line->credit?->getAmount() ?? '0.00') - (float) ($line->debit?->getAmount() ?? '0.00');
            $total = number_format((float) $total + $amount, 2, '.', '');
        }

        return $total;
    }

    /**
     * Meldung je Empfänger-USt-IdNr.
     *
     * Ein Umsatz ohne USt-IdNr. ist ein **Klärungsfall**: Ohne sie ist die
     * Steuerfreiheit der Lieferung nicht nachweisbar (§ 6a UStG), und die
     * Meldung wäre unvollständig.
     *
     * @return array{rows: list<array<string, mixed>>, total: string, unclear: list<string>, interval: VatFilingInterval}
     */
    public function report(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        /** @var array<string, array{vat_id: string, name: string, amount: string}> $rows */
        $rows = [];
        $unclear = [];
        $total = '0.00';

        foreach ($this->lines($organization, $from, $to) as $line) {
            $amount = (float) ($line->credit?->getAmount() ?? '0.00') - (float) ($line->debit?->getAmount() ?? '0.00');
            $total = number_format((float) $total + $amount, 2, '.', '');

            $customer = $line->counterparty_type === Customer::class && $line->counterparty_id !== null
                ? Customer::query()->find($line->counterparty_id)
                : null;

            $vatId = $customer instanceof Customer ? trim((string) $customer->vat_id) : '';

            if ($vatId === '') {
                $unclear[] = (string) __('accounting.recapitulative.unclear.missing_vat_id', [
                    'entry' => (string) ($line->entry !== null ? $line->entry->journal_no : $line->accounting_entry_id),
                    'customer' => $customer instanceof Customer ? $customer->name : (string) __('accounting.recapitulative.unclear.unknown_customer'),
                ]);

                continue;
            }

            $rows[$vatId] ??= ['vat_id' => $vatId, 'name' => $customer instanceof Customer ? (string) $customer->name : '', 'amount' => '0.00'];
            $rows[$vatId]['amount'] = number_format((float) $rows[$vatId]['amount'] + $amount, 2, '.', '');
        }

        ksort($rows);

        return [
            'rows' => array_values($rows),
            'total' => $total,
            'unclear' => array_values(array_unique($unclear)),
            'interval' => $this->intervalFor($organization, $to),
        ];
    }

    /**
     * Buchungszeilen mit Kennziffer 41 im Zeitraum.
     *
     * @return \Illuminate\Support\Collection<int, AccountingEntryLine>
     */
    private function lines(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): \Illuminate\Support\Collection {
        $codeIds = AccountingTaxCode::query()
            ->where('organization_id', $organization->id)
            ->where('ustva_base_field', self::FIELD)
            ->pluck('id');

        if ($codeIds->isEmpty()) {
            return collect();
        }

        return AccountingEntryLine::query()
            ->where('accounting_entry_lines.organization_id', $organization->id)
            ->whereIn('accounting_tax_code_id', $codeIds)
            ->whereHas('entry', fn ($query) => $query
                ->whereIn('status', self::POSTED)
                ->whereDate('booked_on', '>=', $from->toDateString())
                ->whereDate('booked_on', '<=', $to->toDateString()))
            ->with('entry')
            ->get();
    }
}
