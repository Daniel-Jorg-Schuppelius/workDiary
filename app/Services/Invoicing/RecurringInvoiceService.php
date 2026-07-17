<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecurringInvoiceService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Invoicing;

use App\Enums\Notification\NotificationEvent;
use App\Models\Contract\Contract;
use App\Models\{Customer, Invoice, InvoiceSchedule, InvoiceScheduleRun};
use App\Services\Finance\BillingModeResolver;
use App\Services\Notification\NotificationDispatcher;
use Carbon\{Carbon, CarbonInterface};
use Illuminate\Support\Facades\DB;

/**
 * Wiederkehrende Rechnungen (MVP-415): erzeugt zum Stichtag ENTWÜRFE aus
 * Abrechnungsplänen — nie Auto-Ausstellung, nie Auto-Versand. Idempotent
 * über `invoice_schedule_runs` (unique je Plan+Periode); verpasste Läufe
 * werden begrenzt nachgeholt. Führt ein externes Programm die Faktura des
 * Kunden (Rechnungshoheit, Feature 045/086), wird der Plan übersprungen
 * und bleibt sichtbar blockiert.
 */
class RecurringInvoiceService {
    /** Sicherheitsgrenze für Nachholläufe je Plan und Aufruf. */
    private const MAX_CATCHUP_RUNS = 24;

    public function __construct(
        private readonly InvoiceGenerator $generator,
        private readonly BillingModeResolver $billingMode,
        private readonly NotificationDispatcher $notifications,
    ) {}

    /**
     * Alle fälligen Pläne abarbeiten (Konsole: org-übergreifend).
     *
     * @return array{created: int, blocked: int, ended: int}
     */
    public function generateDue(?CarbonInterface $today = null): array {
        $today = Carbon::parse(($today ?? Carbon::today())->toDateString());
        $created = 0;
        $blocked = 0;
        $ended = 0;

        $schedules = InvoiceSchedule::query()
            ->active()
            ->whereDate('next_run_on', '<=', $today)
            ->with(['customer', 'contract', 'items'])
            ->get();

        foreach ($schedules as $schedule) {
            $result = $this->processSchedule($schedule, $today);
            $created += $result['created'];
            $blocked += $result['blocked'] ? 1 : 0;
            $ended += $result['ended'] ? 1 : 0;
        }

        return ['created' => $created, 'blocked' => $blocked, 'ended' => $ended];
    }

    /** @return array{created: int, blocked: bool, ended: bool} */
    private function processSchedule(InvoiceSchedule $schedule, Carbon $today): array {
        $created = 0;

        /** @var Customer|null $customer */
        $customer = $schedule->customer;
        if ($customer === null) {
            return ['created' => 0, 'blocked' => false, 'ended' => false];
        }

        // Rechnungshoheit: führt ein externes Programm (Lexoffice/DATEV/
        // InvoicePlane, Feature 086 §5) die Faktura, läuft KEIN lokaler Plan
        // für dieselbe Quelle — nicht nachholen, sichtbar blockiert lassen.
        if ($this->billingMode->effectiveFor($customer)->isExternal()) {
            return ['created' => 0, 'blocked' => true, 'ended' => false];
        }

        $runs = 0;
        while ($schedule->status === InvoiceSchedule::STATUS_ACTIVE
            && $schedule->next_run_on->lessThanOrEqualTo($today)
            && $runs < self::MAX_CATCHUP_RUNS
        ) {
            // Plan- oder Vertragsende beendet den Plan mit Audit statt still zu laufen.
            if ($this->hasEnded($schedule)) {
                $schedule->update(['status' => InvoiceSchedule::STATUS_ENDED]);

                return ['created' => $created, 'blocked' => false, 'ended' => true];
            }

            if ($this->runOnce($schedule)) {
                $created++;
            }

            $schedule->update([
                'last_run_on' => $today->toDateString(),
                'next_run_on' => $schedule->addInterval($schedule->next_run_on)->toDateString(),
            ]);
            $schedule->refresh();
            $runs++;
        }

        return ['created' => $created, 'blocked' => false, 'ended' => false];
    }

    private function hasEnded(InvoiceSchedule $schedule): bool {
        if ($schedule->end_on !== null && $schedule->next_run_on->greaterThan($schedule->end_on)) {
            return true;
        }

        /** @var Contract|null $contract */
        $contract = $schedule->contract;

        return $contract !== null
            && $contract->ends_on !== null
            && $schedule->next_run_on->greaterThan($contract->ends_on);
    }

    /** Einen Lauf idempotent ausführen; true = Entwurf erzeugt. */
    private function runOnce(InvoiceSchedule $schedule): bool {
        $period = $schedule->periodFor($schedule->next_run_on);

        return (bool) DB::transaction(function () use ($schedule, $period): bool {
            // Idempotenz: unique (Plan, period_start) — Doppel-Läufe erzeugen nichts.
            $exists = InvoiceScheduleRun::query()
                ->withoutGlobalScopes()
                ->where('invoice_schedule_id', $schedule->id)
                ->whereDate('period_start', $period['start']->toDateString())
                ->exists();
            if ($exists) {
                return false;
            }

            $invoice = $this->createDraft($schedule, $period['start'], $period['end']);

            InvoiceScheduleRun::create([
                'organization_id' => $schedule->organization_id,
                'invoice_schedule_id' => $schedule->id,
                'period_start' => $period['start']->toDateString(),
                'period_end' => $period['end']->toDateString(),
                'invoice_id' => $invoice->id,
            ]);

            $this->notifications->notify(
                NotificationEvent::InvoiceRecurringDraft,
                $invoice,
                null,
                [
                    'title' => (string) __('Rechnungsentwurf aus Abrechnungsplan :title erzeugt', ['title' => $schedule->title]),
                    'title_key' => 'Rechnungsentwurf aus Abrechnungsplan :title erzeugt',
                    'title_params' => ['title' => $schedule->title],
                    'url' => route('invoices.show', $invoice),
                ],
            );

            return true;
        });
    }

    private function createDraft(InvoiceSchedule $schedule, Carbon $periodStart, Carbon $periodEnd): Invoice {
        /** @var Customer $customer */
        $customer = $schedule->customer;
        /** @var \App\Models\Organization $organization */
        $organization = $customer->organization()->firstOrFail();
        $tax = app(TaxResolver::class)->resolve($organization, $customer);

        $invoice = Invoice::create([
            'organization_id' => $schedule->organization_id,
            'customer_id' => $customer->id,
            'number' => $this->generator->nextNumber($schedule->organization_id),
            'status' => Invoice::STATUS_DRAFT,
            'currency' => $customer->currency,
            'tax_rate' => $tax['rate'],
            'is_reverse_charge' => $tax['reverse_charge'],
            'notes' => trim((string) __('Automatisch erzeugter Entwurf aus Abrechnungsplan „:title" (:from – :to).', [
                'title' => $schedule->title,
                'from' => $periodStart->format('d.m.Y'),
                'to' => $periodEnd->format('d.m.Y'),
            ]) . ($tax['note'] !== null ? "\n" . $tax['note'] : '')),
            'created_by' => $schedule->created_by,
        ]);

        $position = 0;
        foreach ($schedule->items as $item) {
            $invoice->items()->create([
                'organization_id' => $schedule->organization_id,
                'service_date' => $periodEnd->toDateString(),
                'description' => $this->replacePlaceholders((string) $item->description, $periodStart, $periodEnd),
                'quantity' => (string) $item->quantity,
                'unit' => $item->unit ?? (string) __('invoicing.unit_hour'),
                'unit_price' => (string) $item->unit_price,
                'discount_percent' => $item->discount_percent,
                'discount_amount' => $item->discount_amount,
                'tax_rate' => $item->tax_rate,
                'tax_category' => $item->tax_category,
                'position' => ++$position,
            ]);
        }

        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return $invoice;
    }

    private function replacePlaceholders(string $description, Carbon $periodStart, Carbon $periodEnd): string {
        return str_replace(
            ['{zeitraum_von}', '{zeitraum_bis}'],
            [$periodStart->format('d.m.Y'), $periodEnd->format('d.m.Y')],
            $description,
        );
    }
}
