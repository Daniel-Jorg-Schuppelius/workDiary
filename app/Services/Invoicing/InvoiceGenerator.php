<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceGenerator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Invoicing;

use App\Enums\Numbering\NumberScope;
use App\Models\{Customer, ForeignCustomer, Invoice, MaterialUsage, Project, TimeEntry};
use App\Services\Numbering\NumberSequenceService;
use App\Support\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Auth, DB};

class InvoiceGenerator {
    public function __construct(
        private readonly NumberSequenceService $numberSequence,
    ) {
    }

    /**
     * Liefert die nächste freie Rechnungsnummer für Jahr + Organisation.
     *
     * Die Sequenz wird im {@see NumberSequenceService} per `lockForUpdate`
     * gegen parallele Vergaben geschützt; der Aufrufer sollte trotzdem in
     * einer Transaktion arbeiten, damit die Erzeugung des Invoice-Records
     * mit der Nummer atomar bleibt.
     *
     * @param  string  $prefixLetter  'R' = Rechnung, 'G' = Gutschrift/Korrekturrechnung
     */
    public function nextNumber(?int $organizationId, ?CarbonInterface $when = null, string $prefixLetter = 'R'): string {
        if ($organizationId === null) {
            // Defensive: Nummern sind mandantengebunden, fallback bleibt stabil.
            $when ??= Carbon::now();

            return sprintf('%s%d-%04d', $prefixLetter, (int) $when->format('Y'), 1);
        }

        $scope = $prefixLetter === 'G' ? NumberScope::CreditNote : NumberScope::Invoice;

        return $this->numberSequence->next($organizationId, $scope, $when);
    }

    /**
     * Generate a draft invoice from billable, not-yet-exported time entries
     * for the given customer (and optionally project / foreign customer)
     * within a date range.
     *
     * @param  array{from?: string|CarbonInterface|null, to?: string|CarbonInterface|null}  $range
     */
    public function fromTimeEntries(Customer $customer, ?Project $project, array $range = [], ?ForeignCustomer $foreignCustomer = null): Invoice {
        return DB::transaction(function () use ($customer, $project, $range, $foreignCustomer): Invoice {
            $notes = null;
            if ($foreignCustomer !== null) {
                $notes = (string) __('Endkunde: :name', ['name' => $foreignCustomer->company ?: $foreignCustomer->name]);
            }

            $invoice = Invoice::create([
                'organization_id' => $customer->organization_id,
                'customer_id' => $customer->id,
                'project_id' => $project?->id,
                'foreign_customer_id' => $foreignCustomer?->id,
                'number' => $this->nextNumber($customer->organization_id),
                'status' => Invoice::STATUS_DRAFT,
                'currency' => $customer->currency ?: (string) Setting::get('invoicing.default_currency', 'EUR'),
                'tax_rate' => (string) Setting::get('invoicing.default_tax_rate', '19.00'),
                'notes' => $notes,
                'created_by' => Auth::id(),
            ]);

            $query = TimeEntry::query()
                ->where('billable', true)
                ->where('exported', false)
                ->whereHas('project', fn($q) => $q->where('customer_id', $customer->id)
                    ->when($foreignCustomer !== null, fn($q) => $q->where('foreign_customer_id', $foreignCustomer?->id)));

            if ($project !== null) {
                $query->where('project_id', $project->id);
            }
            if (! empty($range['from'])) {
                $query->where('date', '>=', Carbon::parse($range['from'])->toDateString());
            }
            if (! empty($range['to'])) {
                $query->where('date', '<=', Carbon::parse($range['to'])->toDateString());
            }

            $entries = $query
                ->with(['project.parent', 'project.customer'])
                ->orderBy('date')
                ->get();

            $blocks = app(BillableTimeAggregator::class)->aggregate($entries);
            $entriesById = $entries->keyBy('id');

            $position = 0;
            foreach ($blocks as $block) {
                $hours = $block->billedHours();
                if ($hours <= 0) {
                    continue;
                }

                // Stundensatz aus der tatsächlich gearbeiteten Zeit; auf die
                // aufgerundeten billedHours angewendet erhöht die Taktung den
                // Betrag. Fallback auf Eintrags-/Kunden-Stundensatz.
                $primary = $entriesById->get($block->primaryEntryId);
                $rate = $block->hourlyRate()
                    ?? (float) ($primary?->hourly_rate ?: $customer->hourly_rate ?: 0);

                $description = $this->describeBlock($block, $primary);

                $serviceDate = $block->firstStart?->toDateString() ?? optional($primary?->date)->toDateString();

                $item = $invoice->items()->create([
                    'time_entry_id' => $block->primaryEntryId,
                    'service_date' => $serviceDate,
                    'description' => $description,
                    'quantity' => (string) $hours,
                    'unit' => (string) __('invoicing.unit_hour'),
                    'unit_price' => (string) $rate,
                    'position' => ++$position,
                ]);

                $item->timeEntries()->sync($block->entryIds);
            }

            // Anfahrt der Touren dieses Zeitraums (Leistungstage bevorzugt).
            $this->appendTravelCharges($invoice, $customer, $project, $range, $foreignCustomer, pureMaterialOnly: false, position: $position);

            $invoice->load('items');
            $invoice->recalculate();
            $invoice->save();

            return $invoice;
        });
    }

    /**
     * Erzeugt einen Materialrechnungs-Entwurf aus noch nicht abgerechneten
     * MaterialUsages (über die Timesheets des Kunden/Projekts im Zeitraum).
     *
     * Material wird getrennt von der Leistung abgerechnet: eigene Rechnung mit
     * Kategorie 'material' und Lieferdatum/-zeitraum (= Timesheet-work_date).
     *
     * @param  array{from?: string|CarbonInterface|null, to?: string|CarbonInterface|null}  $range
     */
    public function fromMaterialUsages(Customer $customer, ?Project $project, array $range = [], ?ForeignCustomer $foreignCustomer = null): Invoice {
        return DB::transaction(function () use ($customer, $project, $range, $foreignCustomer): Invoice {
            $notes = null;
            if ($foreignCustomer !== null) {
                $notes = (string) __('Endkunde: :name', ['name' => $foreignCustomer->company ?: $foreignCustomer->name]);
            }

            $invoice = Invoice::create([
                'organization_id' => $customer->organization_id,
                'customer_id' => $customer->id,
                'project_id' => $project?->id,
                'foreign_customer_id' => $foreignCustomer?->id,
                'number' => $this->nextNumber($customer->organization_id),
                'status' => Invoice::STATUS_DRAFT,
                'category' => Invoice::CATEGORY_MATERIAL,
                'currency' => $customer->currency ?: (string) Setting::get('invoicing.default_currency', 'EUR'),
                'tax_rate' => (string) Setting::get('invoicing.default_tax_rate', '19.00'),
                'notes' => $notes,
                'created_by' => Auth::id(),
            ]);

            $usages = MaterialUsage::query()
                ->where('billed', false)
                ->whereHas('timesheet', function ($q) use ($customer, $project, $foreignCustomer, $range): void {
                    $q->whereHas('project', function ($p) use ($customer, $foreignCustomer): void {
                        $p->where('customer_id', $customer->id)
                            ->when($foreignCustomer !== null, fn($p) => $p->where('foreign_customer_id', $foreignCustomer?->id));
                    });
                    if ($project !== null) {
                        $q->where('project_id', $project->id);
                    }
                    if (! empty($range['from'])) {
                        $q->where('work_date', '>=', Carbon::parse($range['from'])->toDateString());
                    }
                    if (! empty($range['to'])) {
                        $q->where('work_date', '<=', Carbon::parse($range['to'])->toDateString());
                    }
                })
                ->with('timesheet:id,work_date')
                ->get();

            $position = 0;
            foreach ($usages as $usage) {
                if ((float) $usage->line_total_net <= 0 && (float) ($usage->unit_price ?? 0) <= 0) {
                    continue;
                }

                $invoice->items()->create([
                    'material_usage_id' => $usage->id,
                    'service_date' => optional($usage->timesheet?->work_date)->toDateString(),
                    'description' => trim((string) $usage->description) ?: (string) __('Material'),
                    'quantity' => (string) $usage->quantity,
                    'unit' => $usage->unit ?: (string) __('invoicing.unit_piece'),
                    'unit_price' => (string) ($usage->unit_price ?? '0'),
                    'position' => ++$position,
                ]);

                $usage->billed = true;
                $usage->saveQuietly();
            }

            // Anfahrt nur für reine Materialtage (Leistungstage bleiben der
            // Leistungsrechnung vorbehalten).
            $this->appendTravelCharges($invoice, $customer, $project, $range, $foreignCustomer, pureMaterialOnly: true, position: $position);

            $invoice->load('items');
            $invoice->recalculate();
            $invoice->save();

            return $invoice;
        });
    }

    /**
     * Hängt Anfahrt-Positionen für noch nicht abgerechnete Touren des Kunden im
     * Zeitraum an. Markiert die Tour als abgerechnet (Sperre gegen Doppelung).
     *
     * @param  array{from?: string|CarbonInterface|null, to?: string|CarbonInterface|null}  $range
     */
    private function appendTravelCharges(
        Invoice $invoice,
        Customer $customer,
        ?Project $project,
        array $range,
        ?ForeignCustomer $foreignCustomer,
        bool $pureMaterialOnly,
        int &$position,
    ): void {
        $charges = app(\App\Services\Travel\TravelChargeService::class)
            ->chargesForRange($customer, $project, $range, $foreignCustomer, $pureMaterialOnly);

        foreach ($charges as $charge) {
            $invoice->items()->create([
                'tour_id' => $charge->tour->id,
                'service_date' => $charge->date->toDateString(),
                'description' => $charge->description,
                'quantity' => (string) $charge->quantity,
                'unit' => $charge->unit,
                'unit_price' => (string) $charge->unitPrice,
                'position' => ++$position,
            ]);

            $charge->tour->travel_billed = true;
            $charge->tour->saveQuietly();
        }
    }

    /**
     * Positions-Beschreibung für einen Block. Einzeleintrag: Eintrags-Beschreibung
     * (Fallback "Leistung am <Datum>"). Zusammengefasster Block: Projektname +
     * Tätigkeitsart + Datumsspanne.
     */
    private function describeBlock(BillingBlock $block, ?TimeEntry $primary): string {
        if (count($block->entryIds) <= 1) {
            $date = $block->firstStart ?? $primary?->date;

            return trim((string) ($block->description
                ?: __('invoicing.service_on', ['date' => optional($date)->format('d.m.Y')])));
        }

        $projectName = $block->project?->name ?: (string) __('Leistung');
        $kindSuffix = $block->kind !== null ? ' [' . $block->kind->value . ']' : '';
        $from = $block->firstStart;
        $to = $block->lastEnd;

        if ($from !== null && $to !== null && $from->toDateString() !== $to->toDateString()) {
            $span = sprintf('%s – %s', $from->format('d.m.Y'), $to->format('d.m.Y'));
        } else {
            $span = optional($from)->format('d.m.Y') ?? '';
        }

        return trim(sprintf('%s%s%s', $projectName, $kindSuffix, $span !== '' ? ' (' . $span . ')' : ''));
    }

    /**
     * Erzeugt eine Gutschrift (Korrekturrechnung) zu einer bezahlten Rechnung.
     *
     * - Kopiert alle Positionen mit NEGATIVEN Mengen (DE-Buchhaltungsstandard).
     * - Eigene Rechnungsnummer mit Prefix 'G' (z.B. G2026-0001).
     * - parent_invoice_id verweist auf das Original.
     * - Status startet als 'draft' — muss vom Benutzer gestellt werden.
     *
     * @throws \LogicException Wenn das Original nicht bezahlt ist oder bereits
     *                         eine Gutschrift existiert.
     */
    public function creditNoteFor(Invoice $original, ?int $userId = null): Invoice {
        if (! $original->needsCreditNoteToCancel()) {
            throw new \LogicException('Original invoice is not eligible for credit note (status: ' . $original->status . ')');
        }

        return DB::transaction(function () use ($original, $userId): Invoice {
            $original->loadMissing('items');

            $credit = Invoice::create([
                'organization_id' => $original->organization_id,
                'customer_id' => $original->customer_id,
                'project_id' => $original->project_id,
                'number' => $this->nextNumber($original->organization_id, prefixLetter: 'G'),
                'status' => Invoice::STATUS_DRAFT,
                'type' => Invoice::TYPE_CREDIT_NOTE,
                'category' => $original->category,
                'parent_invoice_id' => $original->id,
                'currency' => $original->currency,
                'tax_rate' => (string) $original->tax_rate,
                'notes' => __('Korrekturrechnung zu Rechnung :nr vom :date', [
                    'nr' => $original->number,
                    'date' => optional($original->issued_on ?? $original->created_at)->format('d.m.Y'),
                ]),
                'created_by' => $userId ?? Auth::id(),
            ]);

            $position = 0;
            foreach ($original->items as $item) {
                $credit->items()->create([
                    'organization_id' => $original->organization_id,
                    'service_date' => $item->service_date?->toDateString(),
                    'description' => $item->description,
                    'quantity' => (string) (-1 * (float) $item->quantity),
                    'unit' => $item->unit,
                    'unit_price' => (string) $item->unit_price,
                    'position' => ++$position,
                    // bewusst KEINE time_entry_id / expense_id — Zeit/Spese bleibt am Original
                ]);
            }

            $credit->load('items');
            $credit->recalculate();
            $credit->save();

            return $credit;
        });
    }
}
