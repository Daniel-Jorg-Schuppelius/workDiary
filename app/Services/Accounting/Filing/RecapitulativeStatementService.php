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
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\Money;

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
    public const QUARTERLY_THRESHOLD = '50000.00';

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
            $sum = $this->totalFor($organization, $from, $from->addMonths(2)->endOfMonth());

            if (NumberHelper::comparePrecise($sum, self::QUARTERLY_THRESHOLD, 2) > 0) {
                return VatFilingInterval::Monthly;
            }
        }

        return VatFilingInterval::Quarterly;
    }

    /**
     * Summe der innergemeinschaftlichen Lieferungen im Zeitraum.
     *
     * @return numeric-string
     */
    public function totalFor(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): string {
        $lines = $this->lines($organization, $from, $to);

        // Erlöse stehen im Haben: Haben − Soll je Zeile, exakt summiert.
        return Money::sum(
            $lines->map(static fn (AccountingEntryLine $line): Money => $line->signedAmount()->negated()),
            $lines->first()->currency ?? CurrencyCode::Euro,
        )->getAmount();
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
        /** @var array<string, array{vat_id: string, name: string, amount: numeric-string}> $rows */
        $rows = [];
        $unclear = [];
        $lines = $this->lines($organization, $from, $to);
        $total = Money::zero($lines->first()->currency ?? CurrencyCode::Euro);

        foreach ($lines as $line) {
            $amount = $line->signedAmount()->negated();
            $total = $total->plus($amount);

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
            $rows[$vatId]['amount'] = NumberHelper::addPrecise($rows[$vatId]['amount'], $amount->getAmount(), 2);
        }

        ksort($rows);

        return [
            'rows' => array_values($rows),
            'total' => $total->getAmount(),
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
