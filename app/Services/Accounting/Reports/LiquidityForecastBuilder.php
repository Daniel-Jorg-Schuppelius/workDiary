<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LiquidityForecastBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Reports;

use App\Enums\Finance\{FilingObligationKind, FilingObligationStatus, OpenItemDirection, PaymentRunKind, PaymentRunStatus, RecurringRunStatus, RecurringTemplateKind, RecurringTemplateStatus, SettlementKind};
use App\Models\Accounting\{AccountingFilingObligation, AccountingOpenItem, AccountingRecurringRun, AccountingRecurringTemplate, AccountingVatExtension};
use App\Models\AssetFinance\AssetFinanceRateSchedule;
use App\Models\{Customer, IncomingEInvoice, InvoiceSchedule, Organization};
use App\Models\Finance\PaymentRun;
use App\Services\Accounting\Filing\{VatFilingPeriodService, VatReturnService};
use App\Support\Query\DateRange;
use Carbon\{CarbonImmutable, CarbonInterface};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\{Decimal, Money};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * 13-Wochen-Liquiditätsvorschau (Feature 136, MVP-701).
 *
 * Startsaldo = Ist-Salden Bank/Kasse ({@see LiquidityBuilder}); danach je
 * ISO-Woche die erwarteten Ein- und Auszahlungen aus sieben Quellen und der
 * kumulierte Saldo. Überfällige Posten fallen in die laufende Woche — sie
 * sind nicht weniger fällig, nur später.
 *
 * Zahlungsverhalten: erwartetes Zahldatum eines Debitoren-Postens =
 * Fälligkeit + mittlerer Verzug des Kunden aus den Zahlungsausgleichen der
 * letzten 12 Monate (nur Verzug, verfrühte Zahlungen zählen 0 — geplant wird
 * konservativ); ohne Historie gilt die Fälligkeit. Kreditoren: Skontotermin
 * mit gekürztem Betrag, solange er noch erreichbar ist, sonst Fälligkeit.
 *
 * Die Vorschau ist eine Erwartung, kein Kontostand — der Vorbehalt steht auf
 * jeder Ausgabe.
 *
 * @phpstan-type ForecastItem array{source: string, direction: 'in'|'out', amount: numeric-string, expected_on: CarbonImmutable, label: string, note: ?string}
 * @phpstan-type ForecastBucket array{key: string, label: string, from: CarbonImmutable, to: CarbonImmutable, inflow: numeric-string, outflow: numeric-string, net: numeric-string, closing: numeric-string, sources: array<string, array{in: numeric-string, out: numeric-string}>, items: list<ForecastItem>}
 */
class LiquidityForecastBuilder extends AbstractAccountingReportBuilder {
    /** Quellen in Anzeigereihenfolge. */
    public const SOURCES = ['receivables', 'payables', 'recurring', 'invoice_schedules', 'payment_runs', 'finance_rates', 'filings'];

    /** Zulässige Horizonte in Wochen. */
    public const HORIZONS = [13, 26];

    public const DEFAULT_WEEKS = 13;

    /** Zahlungsziel einer Rechnung aus einem Rechnungsplan — wie {@see \App\Services\Invoicing\InvoiceIssueService}. */
    private const DEFAULT_PAYMENT_TERMS_DAYS = 14;

    /** Rückschau für das Zahlungsverhalten. */
    private const HISTORY_MONTHS = 12;

    public function __construct(
        private readonly LiquidityBuilder $liquidity,
        private readonly VatFilingPeriodService $periods,
        private readonly VatReturnService $returns,
    ) {}

    /**
     * @return array{as_of: CarbonImmutable, weeks: int, from: CarbonImmutable, to: CarbonImmutable, opening_balance: numeric-string, buckets: list<ForecastBucket>, totals: array{inflow: numeric-string, outflow: numeric-string, closing: numeric-string, min_closing: numeric-string, min_week: string, items: int}, sources: list<string>}
     */
    public function build(Organization $organization, CarbonImmutable $asOf, int $weeks = self::DEFAULT_WEEKS): array {
        $weeks = in_array($weeks, self::HORIZONS, true) ? $weeks : self::DEFAULT_WEEKS;
        $asOf = $asOf->startOfDay();
        $from = $asOf->startOfWeek(CarbonInterface::MONDAY);
        $to = $from->addWeeks($weeks)->subDay();

        $opening = Decimal::of($this->liquidity->build($organization, $asOf)['cash_total'], 2)->getValue();

        $delays = $this->delays($organization, $asOf);
        $items = [
            ...$this->receivables($organization, $delays),
            ...$this->payables($organization, $asOf),
            ...$this->recurring($organization, $to),
            ...$this->invoiceSchedules($organization, $to, $delays),
            ...$this->paymentRuns($organization, $asOf),
            ...$this->financeRates($organization, $to),
            ...$this->filings($organization, $to),
        ];

        $inflows = array_fill(0, $weeks, '0.00');
        $outflows = array_fill(0, $weeks, '0.00');
        /** @var array<int, array<string, array{in: numeric-string, out: numeric-string}>> $bySource */
        $bySource = array_fill(0, $weeks, array_fill_keys(self::SOURCES, ['in' => '0.00', 'out' => '0.00']));
        /** @var array<int, list<ForecastItem>> $bucketItems */
        $bucketItems = array_fill(0, $weeks, []);
        $counted = 0;

        foreach ($items as $item) {
            $index = $item['expected_on']->lessThan($from) ? 0 : intdiv((int) $from->diffInDays($item['expected_on']), 7);
            if ($index >= $weeks) {
                continue;
            }
            if ($item['expected_on']->lessThan($asOf)) {
                $item['note'] = (string) __('accounting.reports.forecast.note.overdue');
            }
            $cell = $bySource[$index][$item['source']];
            if ($item['direction'] === 'in') {
                $inflows[$index] = NumberHelper::addPrecise($inflows[$index], $item['amount'], 2);
                $cell['in'] = NumberHelper::addPrecise($cell['in'], $item['amount'], 2);
            } else {
                $outflows[$index] = NumberHelper::addPrecise($outflows[$index], $item['amount'], 2);
                $cell['out'] = NumberHelper::addPrecise($cell['out'], $item['amount'], 2);
            }
            $bySource[$index][$item['source']] = $cell;
            $bucketItems[$index][] = $item;
            $counted++;
        }

        $buckets = [];
        $closing = $opening;
        $inflow = '0.00';
        $outflow = '0.00';
        $minClosing = $opening;
        $minWeek = '';
        for ($i = 0; $i < $weeks; $i++) {
            $start = $from->addWeeks($i);
            $net = NumberHelper::subtractPrecise($inflows[$i], $outflows[$i], 2);
            $closing = NumberHelper::addPrecise($closing, $net, 2);
            $weekItems = $bucketItems[$i];
            usort($weekItems, static fn (array $a, array $b): int => $a['expected_on'] <=> $b['expected_on']);
            $label = 'KW ' . $start->isoWeek;
            $buckets[] = [
                'key' => $start->format('o-\WW'),
                'label' => $label,
                'from' => $start,
                'to' => $start->addDays(6),
                'inflow' => $inflows[$i],
                'outflow' => $outflows[$i],
                'net' => $net,
                'closing' => $closing,
                'sources' => $bySource[$i],
                'items' => $weekItems,
            ];
            $inflow = NumberHelper::addPrecise($inflow, $inflows[$i], 2);
            $outflow = NumberHelper::addPrecise($outflow, $outflows[$i], 2);
            if (NumberHelper::comparePrecise($closing, $minClosing, 2) < 0) {
                $minClosing = $closing;
                $minWeek = $label;
            }
        }

        return [
            'as_of' => $asOf,
            'weeks' => $weeks,
            'from' => $from,
            'to' => $to,
            'opening_balance' => $opening,
            'buckets' => $buckets,
            'totals' => [
                'inflow' => $inflow,
                'outflow' => $outflow,
                'closing' => $closing,
                'min_closing' => $minClosing,
                'min_week' => $minWeek,
                'items' => $counted,
            ],
            'sources' => self::SOURCES,
        ];
    }

    /**
     * Mittlerer Verzug je Debitor (Kalendertage, ≥ 0) aus Zahlungsausgleichen
     * der letzten 12 Monate; Schlüssel `{counterparty_type}:{id}`.
     *
     * @return array<string, int>
     */
    private function delays(Organization $organization, CarbonImmutable $asOf): array {
        $rows = DB::table('accounting_open_item_settlements as s')
            ->join('accounting_open_items as i', 'i.id', '=', 's.accounting_open_item_id')
            ->where('s.organization_id', $organization->id)
            ->where('s.kind', SettlementKind::Payment->value)
            ->whereNull('s.reverses_settlement_id')
            ->where('i.direction', OpenItemDirection::Receivable->value)
            ->whereNotNull('i.due_date')
            ->whereNotNull('i.counterparty_id')
            ->where('s.booked_on', '>=', DateRange::day($asOf->subMonths(self::HISTORY_MONTHS)))
            ->get(['i.counterparty_type', 'i.counterparty_id', 'i.due_date', 's.booked_on']);

        $samples = [];
        foreach ($rows as $row) {
            $due = CarbonImmutable::parse((string) $row->due_date)->startOfDay();
            $paid = CarbonImmutable::parse((string) $row->booked_on)->startOfDay();
            // Nur Verzug zählt: verfrüht gezahlt = 0, sonst wäre die Vorschau
            // durch einzelne Vorauszahlungen optimistischer als das Muster.
            $samples[$row->counterparty_type . ':' . $row->counterparty_id][] = max(0, (int) $due->diffInDays($paid, false));
        }

        $delays = [];
        foreach ($samples as $key => $days) {
            $delays[$key] = (int) round(array_sum($days) / count($days));
        }

        return $delays;
    }

    /**
     * @param  array<string, int>  $delays
     * @return list<ForecastItem>
     */
    private function receivables(Organization $organization, array $delays): array {
        $items = [];
        $openItems = AccountingOpenItem::query()
            ->where('organization_id', $organization->id)
            ->where('direction', OpenItemDirection::Receivable->value)
            ->stillOpen()
            ->with('counterparty')
            ->orderBy('due_date')
            ->get();

        foreach ($openItems as $openItem) {
            $amount = $openItem->open_amount;
            if (! $amount instanceof Money || ! $amount->isPositive()) {
                continue;
            }
            $delay = $delays[$openItem->counterparty_type . ':' . $openItem->counterparty_id] ?? 0;
            $base = $this->dueOf($openItem);
            $items[] = [
                'source' => 'receivables',
                'direction' => 'in',
                'amount' => $amount->getAmount(),
                'expected_on' => $base->addDays($delay),
                'label' => trim((string) ($openItem->document_reference ?? '') . ' ' . $this->partyName($openItem->counterparty)),
                'note' => $delay > 0 ? (string) __('accounting.reports.forecast.note.delay', ['days' => $delay]) : null,
            ];
        }

        return $items;
    }

    /** @return list<ForecastItem> */
    private function payables(Organization $organization, CarbonImmutable $asOf): array {
        // Eingangsrechnungen in einem freigegebenen Zahllauf zählen dort — der
        // offene Posten würde die Auszahlung sonst verdoppeln.
        $inRuns = IncomingEInvoice::query()
            ->where('organization_id', $organization->id)
            ->whereIn('paid_in_run_id', $this->committedRunIds($organization))
            ->pluck('id')
            ->map(intval(...))
            ->all();

        $items = [];
        $openItems = AccountingOpenItem::query()
            ->where('organization_id', $organization->id)
            ->where('direction', OpenItemDirection::Payable->value)
            ->stillOpen()
            ->with(['counterparty', 'source'])
            ->orderBy('due_date')
            ->get();

        foreach ($openItems as $openItem) {
            $amount = $openItem->open_amount;
            if (! $amount instanceof Money || ! $amount->isPositive()) {
                continue;
            }
            $source = $openItem->source;
            if ($source instanceof IncomingEInvoice && in_array((int) $source->id, $inRuns, true)) {
                continue;
            }

            $expected = $this->dueOf($openItem);
            $note = null;
            if ($source instanceof IncomingEInvoice) {
                $deadline = $this->discountDeadline($source);
                if ($deadline !== null && $deadline->greaterThanOrEqualTo($asOf)) {
                    $amount = $amount->minusPercentage((float) $source->discount_percent);
                    $expected = $deadline;
                    $note = (string) __('accounting.reports.forecast.note.discount', ['percent' => NumberHelper::toGermanFormat((float) $source->discount_percent, 2)]);
                }
            }

            $items[] = [
                'source' => 'payables',
                'direction' => 'out',
                'amount' => $amount->getAmount(),
                'expected_on' => $expected,
                'label' => trim((string) ($openItem->document_reference ?? '') . ' ' . $this->partyName($openItem->counterparty)),
                'note' => $note,
            ];
        }

        return $items;
    }

    /** Skontofrist einer Eingangsrechnung — null ohne Kondition. */
    private function discountDeadline(IncomingEInvoice $invoice): ?CarbonImmutable {
        $days = (int) ($invoice->discount_days ?? 0);
        if ($days <= 0 || (float) ($invoice->discount_percent ?? 0) <= 0 || $invoice->issue_date === null) {
            return null;
        }

        return CarbonImmutable::parse($invoice->issue_date->toDateString())->addDays($days);
    }

    /**
     * Belegerwartungen: bereits erzeugte Läufe plus die noch nicht erzeugten
     * Termine der Vorlage bis zum Horizont. Buchungsvorlagen haben keine
     * Zahlungsrichtung und bleiben außen vor.
     *
     * @return list<ForecastItem>
     */
    private function recurring(Organization $organization, CarbonImmutable $to): array {
        $templates = AccountingRecurringTemplate::query()
            ->where('organization_id', $organization->id)
            ->where('kind', RecurringTemplateKind::DocumentExpectation->value)
            ->where('status', RecurringTemplateStatus::Active->value)
            ->get();
        if ($templates->isEmpty()) {
            return [];
        }

        $runs = AccountingRecurringRun::query()
            ->where('organization_id', $organization->id)
            ->whereIn('accounting_recurring_template_id', $templates->pluck('id')->all())
            ->where('due_on', '<=', DateRange::day($to))
            ->get()
            ->groupBy('accounting_recurring_template_id');

        $items = [];
        foreach ($templates as $template) {
            $expected = $template->expected_amount;
            $known = [];
            /** @var \Illuminate\Support\Collection<int, AccountingRecurringRun> $templateRuns */
            $templateRuns = $runs->get($template->id, collect());
            foreach ($templateRuns as $run) {
                $known[(string) $run->period_key] = true;
                if (! in_array($run->status, [RecurringRunStatus::Expected, RecurringRunStatus::DraftCreated, RecurringRunStatus::Blocked], true)) {
                    continue;
                }
                $amount = $run->expected_amount ?? $expected;
                if ($amount instanceof Money && $amount->isPositive()) {
                    $items[] = $this->item('recurring', 'out', $amount, CarbonImmutable::parse($run->due_on->toDateString()), (string) $template->name);
                }
            }

            if (! $expected instanceof Money || ! $expected->isPositive() || $template->next_due_on === null) {
                continue;
            }
            $cursor = CarbonImmutable::parse($template->next_due_on->toDateString());
            $ends = $template->ends_on !== null ? CarbonImmutable::parse($template->ends_on->toDateString()) : null;
            for ($guard = 0; $guard < 60 && $cursor->lessThanOrEqualTo($to); $guard++) {
                if ($ends !== null && $cursor->greaterThan($ends)) {
                    break;
                }
                if (! isset($known[$template->interval->periodKey($cursor)])) {
                    $items[] = $this->item('recurring', 'out', $expected, $cursor, (string) $template->name);
                }
                $cursor = $template->interval->next($cursor);
            }
        }

        return $items;
    }

    /**
     * Rechnungspläne (MVP-415): Ausführungstermine bis zum Horizont, Betrag
     * aus den Positionen, Zahlungseingang = Termin + Zahlungsziel + Verzug.
     *
     * @param  array<string, int>  $delays
     * @return list<ForecastItem>
     */
    private function invoiceSchedules(Organization $organization, CarbonImmutable $to, array $delays): array {
        $items = [];
        $schedules = InvoiceSchedule::query()
            ->where('organization_id', $organization->id)
            ->active()
            ->where('next_run_on', '<=', DateRange::day($to))
            ->with(['items', 'customer'])
            ->get();
        $customerType = (new Customer())->getMorphClass();

        foreach ($schedules as $schedule) {
            $gross = Money::zero(CurrencyCode::Euro);
            foreach ($schedule->items as $line) {
                $unit = $line->unit_price;
                if (! $unit instanceof Money) {
                    continue;
                }
                $quantity = (string) $line->quantity;
                $net = $unit->times(is_numeric($quantity) ? $quantity : '0')->withScale(2);
                if ($line->discount_percent !== null) {
                    $net = $line->discount_percent->subtractFrom($net);
                }
                if ($line->discount_amount instanceof Money) {
                    $net = $net->minus($line->discount_amount);
                }
                $gross = $gross->plus($line->tax_rate !== null ? $line->tax_rate->addTo($net) : $net);
            }
            if (! $gross->isPositive()) {
                continue;
            }

            $delay = $delays[$customerType . ':' . $schedule->customer_id] ?? 0;
            $label = (string) $schedule->title . ' ' . $this->partyName($schedule->customer);
            $cursor = CarbonImmutable::parse($schedule->next_run_on->toDateString());
            for ($guard = 0; $guard < 60 && $cursor->lessThanOrEqualTo($to); $guard++) {
                if ($schedule->end_on !== null && $cursor->greaterThan($schedule->end_on)) {
                    break;
                }
                $items[] = $this->item(
                    'invoice_schedules',
                    'in',
                    $gross,
                    $cursor->addDays(self::DEFAULT_PAYMENT_TERMS_DAYS + $delay),
                    trim($label),
                    $delay > 0 ? (string) __('accounting.reports.forecast.note.delay', ['days' => $delay]) : null,
                );
                $cursor = CarbonImmutable::parse($schedule->addInterval($cursor)->toDateString());
            }
        }

        return $items;
    }

    /** @return list<ForecastItem> */
    private function paymentRuns(Organization $organization, CarbonImmutable $asOf): array {
        $items = [];
        $runs = PaymentRun::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [PaymentRunStatus::Released->value, PaymentRunStatus::Exported->value])
            ->orderBy('execution_date')
            ->get();

        foreach ($runs as $run) {
            $total = (string) ($run->total ?? '0');
            if (NumberHelper::comparePrecise($total, '0', 2) <= 0) {
                continue;
            }
            $items[] = [
                'source' => 'payment_runs',
                'direction' => $run->kind === PaymentRunKind::DirectDebit ? 'in' : 'out',
                'amount' => NumberHelper::roundPrecise($total, 2),
                'expected_on' => $run->execution_date !== null ? CarbonImmutable::parse($run->execution_date->toDateString()) : $asOf,
                'label' => (string) ($run->label ?: $run->message_id ?: $run->kind->label()),
                'note' => null,
            ];
        }

        return $items;
    }

    /** @return list<ForecastItem> */
    private function financeRates(Organization $organization, CarbonImmutable $to): array {
        $items = [];
        $rates = AssetFinanceRateSchedule::query()
            ->where('organization_id', $organization->id)
            ->planned()
            ->where('due_on', '<=', DateRange::day($to))
            ->with('contract')
            ->orderBy('due_on')
            ->get();

        foreach ($rates as $rate) {
            $amount = (string) $rate->amount;
            if (NumberHelper::comparePrecise($amount, '0', 2) <= 0) {
                continue;
            }
            $contract = $rate->contract;
            $items[] = [
                'source' => 'finance_rates',
                'direction' => 'out',
                'amount' => NumberHelper::roundPrecise($amount, 2),
                'expected_on' => CarbonImmutable::parse($rate->due_on->toDateString()),
                'label' => trim((string) ($contract?->getAttribute('number') ?? '') . ' ' . (string) ($contract?->getAttribute('partner_name') ?? '')),
                'note' => null,
            ];
        }

        return $items;
    }

    /**
     * Steuertermine mit bezifferbarem Betrag: USt-Voranmeldung (Zahllast der
     * Periode, negativ = Erstattung) und Sondervorauszahlung. ZM und
     * Jahreserklärung haben keinen Zahlbetrag und bleiben außen vor.
     *
     * @return list<ForecastItem>
     */
    private function filings(Organization $organization, CarbonImmutable $to): array {
        $items = [];
        $obligations = AccountingFilingObligation::query()
            ->where('organization_id', $organization->id)
            ->where('status', FilingObligationStatus::Open->value)
            ->whereIn('kind', [FilingObligationKind::VatAdvance->value, FilingObligationKind::SpecialPrepayment->value])
            ->where('due_on', '<=', DateRange::day($to))
            ->orderBy('due_on')
            ->get();

        foreach ($obligations as $obligation) {
            $label = $obligation->kind->label() . ' ' . $obligation->period_key;
            $dueOn = CarbonImmutable::parse($obligation->due_on->toDateString());

            if ($obligation->kind === FilingObligationKind::SpecialPrepayment) {
                $extension = AccountingVatExtension::query()
                    ->where('organization_id', $organization->id)
                    ->where('year', (int) $obligation->period_key)
                    ->first();
                $amount = $extension?->special_prepayment_amount;
                if ($amount instanceof Money && $amount->isPositive()) {
                    $items[] = $this->item('filings', 'out', $amount, $dueOn, $label);
                }

                continue;
            }

            $period = $this->periods->parse((string) $obligation->period_key);
            if ($period === null) {
                continue;
            }
            $preview = $this->returns->preview($organization, $period);
            $amount = (string) ($preview['remaining'] ?? $preview['payable'] ?? '0.00');
            if (! is_numeric($amount) || NumberHelper::comparePrecise($amount, '0', 2) === 0) {
                continue;
            }
            $items[] = [
                'source' => 'filings',
                'direction' => NumberHelper::isNegativePrecise($amount) ? 'in' : 'out',
                'amount' => NumberHelper::absPrecise(NumberHelper::roundPrecise($amount, 2)),
                'expected_on' => $dueOn,
                'label' => $label,
                'note' => null,
            ];
        }

        return $items;
    }

    /** @return list<int> */
    private function committedRunIds(Organization $organization): array {
        $ids = PaymentRun::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [PaymentRunStatus::Released->value, PaymentRunStatus::Exported->value])
            ->pluck('id')
            ->all();

        return array_values(array_map(static fn ($id): int => (int) $id, $ids));
    }

    /**
     * @param  'in'|'out'  $direction
     * @return ForecastItem
     */
    private function item(string $source, string $direction, Money $amount, CarbonImmutable $expectedOn, string $label, ?string $note = null): array {
        return [
            'source' => $source,
            'direction' => $direction,
            'amount' => $amount->withScale(2)->getAmount(),
            'expected_on' => $expectedOn,
            'label' => trim($label),
            'note' => $note,
        ];
    }

    /** Fälligkeit des Postens; ohne Fälligkeit gilt das Belegdatum. */
    private function dueOf(AccountingOpenItem $openItem): CarbonImmutable {
        $date = $openItem->due_date ?? $openItem->document_date;

        return CarbonImmutable::parse($date->toDateString())->startOfDay();
    }

    private function partyName(?Model $party): string {
        $name = $party?->getAttribute('name');

        return is_string($name) ? $name : '';
    }
}
