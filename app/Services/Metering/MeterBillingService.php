<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MeterBillingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Metering;

use App\Models\{Customer, Invoice, MeterReading};
use App\Models\Metering\{MeterBillingAgreement, MeterBillingRun};
use App\Services\Finance\BillingModeResolver;
use App\Services\Invoicing\{InvoiceGenerator, TaxResolver};
use Carbon\{CarbonImmutable, CarbonInterface};
use Illuminate\Support\Facades\DB;

/**
 * Zählerstands-Faktura (Feature 116, MVP-605).
 *
 * Erzeugt aus den erfassten Ablesungen **Rechnungsentwürfe** — nie fertige
 * Belege. Zwei Gründe, die beide für sich reichen: Wo ein externes System die
 * Faktura führt, gilt die Rechnungshoheit unverändert; und ein automatisch
 * ausgestellter Beleg ließe sich nach GoBD nicht mehr korrigieren, während die
 * häufigste Korrektur genau die vergessene Ablesung ist.
 *
 * **Fehlende Ablesung ist ein Befund, keine Schätzung.** Ohne Endstand
 * entsteht kein Entwurf, sondern ein übersprungener Lauf mit Grund — sonst
 * sähe ein fehlender Entwurf aus wie „noch nicht gelaufen".
 */
class MeterBillingService {
    /** Sicherheitsgrenze für Nachholläufe je Vereinbarung und Aufruf. */
    private const MAX_CATCHUP_RUNS = 24;

    public function __construct(
        private readonly InvoiceGenerator $generator,
        private readonly BillingModeResolver $billingMode,
    ) {}

    /**
     * Alle fälligen Vereinbarungen abarbeiten.
     *
     * @return array{created: int, skipped: int, blocked: int}
     */
    public function generateDue(?CarbonInterface $today = null): array {
        $today = CarbonImmutable::parse(($today ?? CarbonImmutable::today())->toDateString());
        $result = ['created' => 0, 'skipped' => 0, 'blocked' => 0];

        $agreements = MeterBillingAgreement::query()
            ->withoutGlobalScopes()
            ->where('status', MeterBillingAgreement::STATUS_ACTIVE)
            ->whereDate('next_run_on', '<=', $today->toDateString())
            ->with(['customer', 'asset'])
            ->get();

        foreach ($agreements as $agreement) {
            $one = $this->runAgreement($agreement, $today);
            $result['created'] += $one['created'];
            $result['skipped'] += $one['skipped'];
            $result['blocked'] += $one['blocked'];
        }

        return $result;
    }

    /**
     * Eine Vereinbarung bis zum Stichtag abarbeiten (inkl. Nachholläufen).
     *
     * @return array{created: int, skipped: int, blocked: int}
     */
    public function runAgreement(MeterBillingAgreement $agreement, ?CarbonInterface $today = null): array {
        $today = CarbonImmutable::parse(($today ?? CarbonImmutable::today())->toDateString());
        $customer = $agreement->customer;

        if ($customer === null || ! $agreement->isRunnable()) {
            return ['created' => 0, 'skipped' => 0, 'blocked' => 0];
        }

        // Rechnungshoheit (Feature 045/086): Führt ein externes System die
        // Faktura des Kunden, entsteht hier kein Beleg — die Vereinbarung
        // bleibt sichtbar stehen statt still zu laufen.
        if ($this->billingMode->effectiveFor($customer)->isExternal()) {
            return ['created' => 0, 'skipped' => 0, 'blocked' => 1];
        }

        $created = 0;
        $skipped = 0;
        $loops = 0;

        while ($agreement->isRunnable()
            && $agreement->next_run_on->lessThanOrEqualTo($today)
            && $loops < self::MAX_CATCHUP_RUNS) {
            $loops++;

            $periodEnd = CarbonImmutable::parse($agreement->next_run_on->toDateString());
            $periodStart = $periodEnd->subMonths($agreement->periodMonths())->addDay();

            $outcome = $this->billPeriod($agreement, $periodStart, $periodEnd);
            $outcome === null ? $skipped++ : $created++;

            $next = $periodEnd->addMonths($agreement->periodMonths());
            $agreement->forceFill([
                'last_run_on' => $periodEnd->toDateString(),
                'next_run_on' => $next->toDateString(),
                'status' => $agreement->end_on !== null && $next->greaterThan(CarbonImmutable::parse($agreement->end_on->toDateString()))
                    ? MeterBillingAgreement::STATUS_ENDED
                    : $agreement->status,
            ])->save();
            $agreement->refresh();
        }

        return ['created' => $created, 'skipped' => $skipped, 'blocked' => 0];
    }

    /**
     * Eine Periode abrechnen. Liefert den Entwurf oder null, wenn nichts
     * abzurechnen war (fehlende Ablesung, kein Verbrauch und kein Grundpreis).
     */
    public function billPeriod(MeterBillingAgreement $agreement, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): ?Invoice {
        $customer = $agreement->customer;
        if (! $customer instanceof Customer) {
            return null;
        }

        // Idempotenz: Ein Nachlauf darf keinen zweiten Entwurf erzeugen.
        $existing = MeterBillingRun::query()
            ->withoutGlobalScopes()
            ->where('meter_billing_agreement_id', $agreement->id)
            ->whereDate('period_start', $periodStart->toDateString())
            ->first();
        if ($existing !== null) {
            return $existing->invoice;
        }

        [$consumption, $reason, $estimated] = $this->consumptionFor($agreement, $periodStart, $periodEnd);

        if ($reason !== null) {
            $this->recordRun($agreement, $periodStart, $periodEnd, null, $reason, null);

            return null;
        }

        $billable = max(0.0, $consumption - (float) $agreement->free_units);
        $variable = round($this->priceFor($agreement, $billable), 2);
        $base = round((float) $agreement->base_price, 2);

        if ($base <= 0.0 && $variable <= 0.0) {
            $this->recordRun($agreement, $periodStart, $periodEnd, null, 'nothing_to_bill', $consumption);

            return null;
        }

        $invoice = $this->draft($agreement, $customer, $periodStart, $periodEnd, $consumption, $billable, $base, $variable, $estimated);
        $this->recordRun($agreement, $periodStart, $periodEnd, $invoice, null, $consumption);

        return $invoice;
    }

    /**
     * Verbrauch der Periode aus den Ablesungen.
     *
     * Bewusst NICHT über `MeterReading::consumption` summiert: Dieses Feld ist
     * die Differenz zur jeweils vorigen Ablesung und zählt damit auch den
     * Zeitraum VOR dem Periodenbeginn mit, wenn die letzte Ablesung davor lag.
     * Maßgeblich ist die Differenz zwischen dem letzten Stand VOR der Periode
     * und dem letzten Stand IN der Periode.
     *
     * @return array{0: float, 1: string|null, 2: bool} Verbrauch, Abbruchgrund, geschätzt?
     */
    public function consumptionFor(MeterBillingAgreement $agreement, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): array {
        $readings = MeterReading::query()
            ->withoutGlobalScopes()
            ->where('asset_id', $agreement->asset_id)
            ->orderBy('read_at')
            ->get(['id', 'read_at', 'value', 'is_estimated']);

        $before = $readings->last(fn (MeterReading $r): bool => $r->read_at->lessThan($periodStart->startOfDay()));
        $inPeriod = $readings->filter(fn (MeterReading $r): bool => $r->read_at->betweenIncluded($periodStart->startOfDay(), $periodEnd->endOfDay()));
        $end = $inPeriod->last();

        if ($before === null) {
            return [0.0, 'missing_start_reading', false];
        }
        if ($end === null) {
            return [0.0, 'missing_end_reading', false];
        }

        $consumption = round((float) $end->value - (float) $before->value, 3);
        if ($consumption < 0.0) {
            // Zählerwechsel oder Rücklauf: Ein negativer Verbrauch ist keine
            // Gutschrift, sondern ein Fall für den Menschen.
            return [0.0, 'negative_consumption', false];
        }

        $estimated = (bool) $end->is_estimated || (bool) $before->is_estimated;

        return [$consumption, null, $estimated];
    }

    /**
     * Preis der abzurechnenden Menge. Ohne Staffel gilt der Einheitspreis;
     * mit Staffel wird STUFENWEISE gerechnet (wie bei Stromtarifen), nicht
     * der ganze Verbrauch zum günstigsten Satz — sonst führte eine Einheit
     * mehr zu einer niedrigeren Rechnung.
     */
    public function priceFor(MeterBillingAgreement $agreement, float $billableUnits): float {
        if ($billableUnits <= 0.0) {
            return 0.0;
        }

        $tiers = $this->normalizedTiers($agreement);
        if ($tiers === []) {
            return $billableUnits * (float) $agreement->unit_price;
        }

        $total = 0.0;
        foreach ($tiers as $index => $tier) {
            $from = (float) $tier['from'];
            $to = isset($tiers[$index + 1]) ? $tiers[$index + 1]['from'] : null;
            $upper = $to === null ? $billableUnits : min($billableUnits, (float) $to);
            if ($upper <= $from) {
                continue;
            }
            $total += ($upper - $from) * (float) $tier['price'];
            if ($to === null || $billableUnits <= (float) $to) {
                break;
            }
        }

        return $total;
    }

    /** @return list<array{from: float, price: string}> */
    private function normalizedTiers(MeterBillingAgreement $agreement): array {
        $tiers = [];
        foreach ((array) ($agreement->tiers ?? []) as $tier) {
            if (! is_array($tier) || ! isset($tier['price'])) {
                continue;
            }
            $tiers[] = ['from' => isset($tier['from']) ? (float) $tier['from'] : 0.0, 'price' => (string) $tier['price']];
        }
        usort($tiers, static fn (array $a, array $b): int => $a['from'] <=> $b['from']);

        return $tiers;
    }

    private function draft(
        MeterBillingAgreement $agreement,
        Customer $customer,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
        float $consumption,
        float $billable,
        float $base,
        float $variable,
        bool $estimated,
    ): Invoice {
        return DB::transaction(function () use ($agreement, $customer, $periodStart, $periodEnd, $consumption, $billable, $base, $variable, $estimated): Invoice {
            $tax = app(TaxResolver::class)->resolve($customer->organization()->firstOrFail(), $customer, $periodEnd);

            $invoice = Invoice::query()->create([
                'organization_id' => $agreement->organization_id,
                'customer_id' => $customer->id,
                'project_id' => $agreement->project_id,
                'number' => $this->generator->nextNumber((int) $agreement->organization_id, $periodEnd),
                'status' => Invoice::STATUS_DRAFT,
                'type' => Invoice::TYPE_INVOICE,
                'currency' => $customer->currency,
                'tax_rate' => $tax['rate'],
                'is_reverse_charge' => $tax['reverse_charge'],
                'notes' => $tax['note'],
            ]);

            $position = 1;
            if ($base > 0.0) {
                $invoice->items()->create([
                    'organization_id' => $agreement->organization_id,
                    'service_date' => $periodEnd->toDateString(),
                    'description' => __('metering.line.base', [
                        'title' => $agreement->title,
                        'from' => $periodStart->format('d.m.Y'),
                        'to' => $periodEnd->format('d.m.Y'),
                    ]),
                    'quantity' => '1',
                    'unit' => (string) __('invoicing.unit_flat'),
                    'unit_price' => number_format($base, 4, '.', ''),
                    'tax_category' => $tax['category'],
                    'position' => $position++,
                ]);
            }

            if ($variable > 0.0) {
                // Nachvollziehbarkeit auf der Rechnung: Anfangs-/Endstand,
                // Verbrauch und Freimenge stehen im Positionstext. Ohne diese
                // Angaben ist die Rechnung für den Kunden nicht prüfbar — und
                // Rückfragen kosten mehr, als die Automatik spart.
                $invoice->items()->create([
                    'organization_id' => $agreement->organization_id,
                    'service_date' => $periodEnd->toDateString(),
                    'description' => __('metering.line.usage', [
                        'title' => $agreement->title,
                        'consumption' => rtrim(rtrim(\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($consumption, 3, withThousandsSeparator: true), '0'), ','),
                        'free' => rtrim(rtrim(\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $agreement->free_units, 3, withThousandsSeparator: true), '0'), ','),
                        'unit' => (string) ($agreement->unit ?? ''),
                    ]) . ($estimated ? ' ' . __('metering.line.estimated') : ''),
                    'quantity' => number_format($billable, 3, '.', ''),
                    'unit' => (string) ($agreement->unit ?? __('metering.unit_default')),
                    'unit_price' => number_format($billable > 0.0 ? $variable / $billable : 0.0, 4, '.', ''),
                    'tax_category' => $tax['category'],
                    'position' => $position,
                ]);
            }

            $invoice->load('items');
            $invoice->recalculate();
            $invoice->save();

            $invoice->audit('invoice.metering_drafted', [
                'agreement_id' => $agreement->id,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'consumption' => (string) $consumption,
            ]);

            return $invoice;
        });
    }

    private function recordRun(
        MeterBillingAgreement $agreement,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
        ?Invoice $invoice,
        ?string $reason,
        ?float $consumption,
    ): void {
        MeterBillingRun::query()->create([
            'organization_id' => $agreement->organization_id,
            'meter_billing_agreement_id' => $agreement->id,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'invoice_id' => $invoice?->id,
            'skipped_reason' => $reason,
            'consumption' => $consumption === null ? null : number_format($consumption, 3, '.', ''),
        ]);
    }
}
