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
use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\Organization;
use App\Support\MorphMap;
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
 * Erkannt werden die Umsätze über die Kennziffer des Steuerkennzeichens
 * (MVP-688) — nicht über Kontonummern: 41 innergemeinschaftliche Lieferungen,
 * 21 sonstige Leistungen nach § 3a Abs. 2 UStG (MVP-1622). Die Meldung trennt
 * je Empfänger nach der Art; die Schwelle für den Monatszeitraum zählt nur
 * Lieferungen.
 */
class RecapitulativeStatementService {
    /** § 18a Abs. 1 S. 2 UStG. */
    public const string QUARTERLY_THRESHOLD = '50000.00';

    /** Kennziffer der innergemeinschaftlichen Lieferungen in der UStVA. */
    public const string FIELD = '41';

    /** Kennziffer der im übrigen Gemeinschaftsgebiet steuerpflichtigen sonstigen Leistungen. */
    public const string SERVICES_FIELD = '21';

    public const string KIND_GOODS = 'goods';

    public const string KIND_SERVICES = 'services';

    private const array POSTED = [AccountingEntryStatus::Posted->value, AccountingEntryStatus::Reversed->value];

    /**
     * Meldezeitraum eines Quartals: monatlich, sobald eine der letzten fünf
     * Quartalssummen über der Schwelle liegt.
     */
    public function intervalFor(Organization $organization, CarbonImmutable $date): VatFilingInterval {
        $quarterStart = CarbonImmutable::parse(sprintf('%04d-%02d-01', $date->year, ((int) ceil($date->month / 3) - 1) * 3 + 1));

        for ($back = 0; $back <= 4; $back++) {
            $from = $quarterStart->subMonths(3 * $back);
            $sum = $this->sum($this->lines($organization, $from, $from->addMonths(2)->endOfMonth(), [self::FIELD]));

            if (NumberHelper::comparePrecise($sum, self::QUARTERLY_THRESHOLD, 2) > 0) {
                return VatFilingInterval::Monthly;
            }
        }

        return VatFilingInterval::Quarterly;
    }

    /**
     * Summe der meldepflichtigen Umsätze (Lieferungen und sonstige Leistungen) im Zeitraum.
     *
     * @return numeric-string
     */
    public function totalFor(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): string {
        return $this->sum($this->lines($organization, $from, $to));
    }

    /**
     * Erlöse stehen im Haben: Haben − Soll je Zeile, exakt summiert.
     *
     * @param  \Illuminate\Support\Collection<int, AccountingEntryLine>  $lines
     * @return numeric-string
     */
    private function sum(\Illuminate\Support\Collection $lines): string {
        return Money::sum(
            $lines->map(static fn (AccountingEntryLine $line): Money => $line->signedAmount()->negated()),
            $lines->first()->currency ?? CurrencyCode::Euro,
        )->getAmount();
    }

    /**
     * Meldung je Empfänger-USt-IdNr. und Art (Lieferung oder sonstige Leistung).
     *
     * Ein Umsatz ohne USt-IdNr. ist ein **Klärungsfall**: Ohne sie ist die
     * Steuerfreiheit der Lieferung nicht nachweisbar (§ 6a UStG), und die
     * Meldung wäre unvollständig.
     *
     * @return array{rows: list<array<string, mixed>>, total: string, total_goods: string, total_services: string, unclear: list<string>, interval: VatFilingInterval}
     */
    public function report(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        /** @var array<string, array{vat_id: string, name: string, kind: string, amount: numeric-string}> $rows */
        $rows = [];
        $unclear = [];
        $lines = $this->lines($organization, $from, $to);
        $zero = Money::zero($lines->first()->currency ?? CurrencyCode::Euro);
        $totals = [self::KIND_GOODS => $zero, self::KIND_SERVICES => $zero];

        foreach ($lines as $line) {
            $amount = $line->signedAmount()->negated();
            $kind = $line->taxCode?->ustva_base_field === self::SERVICES_FIELD ? self::KIND_SERVICES : self::KIND_GOODS;
            $totals[$kind] = $totals[$kind]->plus($amount);

            $customer = MorphMap::is($line->counterparty_type, Customer::class) && $line->counterparty_id !== null
                ? Customer::query()->find($line->counterparty_id)
                : null;

            // Gemeldet wird die USt-IdNr. der ausgestellten Rechnung, nicht die heutige des Kunden.
            $invoice = $line->entry?->source;
            $recipient = $invoice instanceof Invoice ? $invoice->recipient() : null;
            $vatId = trim($recipient !== null ? $recipient['vat_id'] : (string) ($customer->vat_id ?? ''));

            if ($vatId === '') {
                $unclear[] = (string) __('accounting.recapitulative.unclear.missing_vat_id', [
                    'entry' => (string) ($line->entry !== null ? $line->entry->journal_no : $line->accounting_entry_id),
                    'customer' => $customer instanceof Customer ? $customer->name : (string) __('accounting.recapitulative.unclear.unknown_customer'),
                ]);

                continue;
            }

            $key = $vatId . '|' . $kind;
            $name = $recipient !== null ? $recipient['name'] : (string) ($customer->name ?? '');
            $rows[$key] ??= ['vat_id' => $vatId, 'name' => $name, 'kind' => $kind, 'amount' => '0.00'];
            $rows[$key]['amount'] = NumberHelper::addPrecise($rows[$key]['amount'], $amount->getAmount(), 2);
        }

        ksort($rows);

        return [
            'rows' => array_values($rows),
            'total' => $totals[self::KIND_GOODS]->plus($totals[self::KIND_SERVICES])->getAmount(),
            'total_goods' => $totals[self::KIND_GOODS]->getAmount(),
            'total_services' => $totals[self::KIND_SERVICES]->getAmount(),
            'unclear' => array_values(array_unique($unclear)),
            'interval' => $this->intervalFor($organization, $to),
        ];
    }

    /**
     * Buchungszeilen mit Kennziffer 41 bzw. 21 im Zeitraum.
     *
     * @param  list<string>  $fields
     * @return \Illuminate\Support\Collection<int, AccountingEntryLine>
     */
    private function lines(Organization $organization, CarbonImmutable $from, CarbonImmutable $to, array $fields = [self::FIELD, self::SERVICES_FIELD]): \Illuminate\Support\Collection {
        $codeIds = AccountingTaxCode::query()
            ->where('organization_id', $organization->id)
            ->whereIn('ustva_base_field', $fields)
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
            ->with(['entry.source', 'taxCode'])
            ->get();
    }
}
