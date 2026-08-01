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
use App\Services\Finance\{BillingModeLockedException, BillingModeResolver};
use App\Services\Numbering\NumberSequenceService;
use Carbon\CarbonInterface;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Auth, DB};

class InvoiceGenerator {
    public function __construct(
        private readonly NumberSequenceService $numberSequence,
    ) {}

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
     * @param  list<int>  $excludedEntryIds  In der Vorschau abgewählte Einträge (MVP-462) —
     *                                       bleiben exported=false und erscheinen im nächsten Lauf.
     */
    public function fromTimeEntries(Customer $customer, ?Project $project, array $range = [], ?ForeignCustomer $foreignCustomer = null, array $excludedEntryIds = []): Invoice {
        $this->assertLocalBillingAllowed($customer);
        $this->assertNotAccountManaged($customer);

        return DB::transaction(function () use ($customer, $project, $range, $foreignCustomer, $excludedEntryIds): Invoice {
            // Per-Kunde serialisieren: verhindert, dass zwei parallele
            // Rechnungsläufe dieselben exported=false-Zeiten / travel_billed=false-
            // Touren doppelt abrechnen (Doppelklick).
            Customer::query()->whereKey($customer->id)->lockForUpdate()->first();

            // Quellposten UNTER Sperre und VOR Nummernvergabe laden (wie im
            // Materialpfad): sonst erzeugt ein Lauf ohne offene Posten eine
            // leere Rechnung samt verbrauchter Nummer.
            $entries = $this->openTimeEntriesQuery($customer, $project, $range, $foreignCustomer)
                ->when($excludedEntryIds !== [], fn($q) => $q->whereNotIn('id', $excludedEntryIds))
                ->lockForUpdate()
                ->get();

            // Anfahrt der Touren dieses Zeitraums (Leistungstage bevorzugt).
            $charges = app(\App\Services\Travel\TravelChargeService::class)
                ->chargesForRange($customer, $project, $range, $foreignCustomer, false);

            if ($entries->isEmpty() && count($charges) === 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'customer_id' => (string) __('Für den gewählten Zeitraum gibt es keine abrechenbaren Zeiten oder Anfahrten.'),
                ]);
            }

            $notes = null;
            if ($foreignCustomer !== null) {
                $notes = (string) __('Endkunde: :name', ['name' => $foreignCustomer->company ?: $foreignCustomer->name]);
            }

            // Länderspezifische Steuerlogik (Restpunkt 68): Katalog + Org-
            // Override, Reverse-Charge bei EU-B2B mit gültiger USt-IdNr.
            $tax = app(TaxResolver::class)->resolve($customer->organization()->firstOrFail(), $customer);
            if ($tax['note'] !== null) {
                $notes = trim(($notes !== null ? $notes . "\n" : '') . $tax['note']);
            }

            $invoice = Invoice::create([
                'organization_id' => $customer->organization_id,
                'customer_id' => $customer->id,
                'project_id' => $project?->id,
                'foreign_customer_id' => $foreignCustomer?->id,
                'number' => $this->nextNumber($customer->organization_id),
                'status' => Invoice::STATUS_DRAFT,
                'currency' => $customer->currency,
                'tax_rate' => $tax['rate'],
                'is_reverse_charge' => $tax['reverse_charge'],
                'notes' => $notes !== '' ? $notes : null,
                'created_by' => Auth::id(),
            ]);

            $blocks = app(BillableTimeAggregator::class)->aggregate($entries);
            $entriesById = $entries->keyBy('id');

            $position = 0;
            $billedEntryIds = [];
            foreach ($blocks as $block) {
                $line = $this->blockLine($block, $customer, $entriesById);
                if ($line === null) {
                    continue;
                }

                $item = $invoice->items()->create([
                    'time_entry_id' => $block->primaryEntryId,
                    'service_date' => $line['service_date'],
                    'description' => $line['description'],
                    'quantity' => (string) $line['hours'],
                    'unit' => (string) __('invoicing.unit_hour'),
                    'unit_price' => (string) $line['rate'],
                    'position' => ++$position,
                ]);

                $item->timeEntries()->sync($block->entryIds);
                $billedEntryIds = array_merge($billedEntryIds, $block->entryIds);
            }

            // Abgerechnete Zeiten markieren — symmetrisch zu Material
            // (billed=true) und Touren (travel_billed=true). Ohne die
            // Markierung fakturiert ein überlappender Folgelauf dieselben
            // Zeiten erneut (Whitebox 2026-07-10, G1).
            if ($billedEntryIds !== []) {
                TimeEntry::query()->whereKey(array_unique($billedEntryIds))->update(['exported' => true]);
            }

            // Anfahrten wurden oben unter Sperre geladen.
            $this->appendTravelCharges($invoice, $charges, $foreignCustomer, $position);

            $invoice->load('items');
            $invoice->recalculate();
            $invoice->save();

            return $invoice;
        });
    }

    /**
     * Gemeinsame Quellposten-Query von Rechnungslauf und Vorschau: offene,
     * abrechenbare Zeiten des Kunden (optional Projekt/Endkunde/Zeitraum).
     *
     * @param  array{from?: string|CarbonInterface|null, to?: string|CarbonInterface|null}  $range
     * @return \Illuminate\Database\Eloquent\Builder<TimeEntry>
     */
    private function openTimeEntriesQuery(Customer $customer, ?Project $project, array $range, ?ForeignCustomer $foreignCustomer): \Illuminate\Database\Eloquent\Builder {
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

        return $query
            ->with(['project.parent', 'project.customer', 'project.foreignCustomer', 'user:id,name'])
            ->orderBy('date');
    }

    /**
     * Positionsdaten eines Blocks: Stundensatz aus der tatsächlich
     * gearbeiteten Zeit; auf die aufgerundeten billedHours angewendet erhöht
     * die Taktung den Betrag. Fallback auf Eintrags-/Kunden-Stundensatz.
     * NULL bei leerer abrechenbarer Menge.
     *
     * @param  \Illuminate\Support\Collection<int|string, TimeEntry>  $entriesById
     * @return array{hours: float, rate: float, description: string, service_date: string|null}|null
     */
    private function blockLine(BillingBlock $block, Customer $customer, \Illuminate\Support\Collection $entriesById): ?array {
        $hours = $block->billedHours();
        if ($hours <= 0) {
            return null;
        }

        $primary = $entriesById->get($block->primaryEntryId);
        $fallbackRate = $primary !== null && $primary->hourly_rate !== null
            ? $primary->hourly_rate
            : $customer->hourly_rate;
        $rate = $block->hourlyRate() ?? $fallbackRate?->toFloat() ?? 0.0;

        return [
            'hours' => $hours,
            'rate' => $rate,
            'description' => $this->bookingLine(
                $this->describeBlock($block, $primary),
                $block->project?->foreignCustomer,
            ),
            'service_date' => $block->firstStart?->toDateString() ?? optional($primary?->date)->toDateString(),
        ];
    }

    /**
     * Read-only-Vorschau des Rechnungslaufs (MVP-462): gleiche Selektion und
     * Blockbildung wie {@see fromTimeEntries}, aber ohne Transaktion, Sperre
     * und Nummernvergabe — es wird nichts verbraucht. Liefert Blöcke samt
     * Einzel-Einträgen für die Ausschluss-Checkboxen sowie Warnsignale
     * (Nachzügler via {@see LateTimeEntryDetector}).
     *
     * @param  array{from?: string|CarbonInterface|null, to?: string|CarbonInterface|null}  $range
     * @return array{
     *   entries: \Illuminate\Database\Eloquent\Collection<int, TimeEntry>,
     *   lines: list<array{description: string, hours: float, rate: float, amount: float, minutes: int, entry_ids: list<int>, project_name: string|null}>,
     *   travel: array{count: int, amount: float},
     *   totals: array{count: int, minutes: int, amount: float},
     *   warnings: array{late_count: int}
     * }
     */
    public function previewTimeEntries(Customer $customer, ?Project $project, array $range = [], ?ForeignCustomer $foreignCustomer = null): array {
        $this->assertLocalBillingAllowed($customer);
        $this->assertNotAccountManaged($customer);

        $entries = $this->openTimeEntriesQuery($customer, $project, $range, $foreignCustomer)->get();
        $charges = app(\App\Services\Travel\TravelChargeService::class)
            ->chargesForRange($customer, $project, $range, $foreignCustomer, false);

        $blocks = app(BillableTimeAggregator::class)->aggregate($entries);
        $entriesById = $entries->keyBy('id');

        $lines = [];
        $amount = 0.0;
        foreach ($blocks as $block) {
            $line = $this->blockLine($block, $customer, $entriesById);
            if ($line === null) {
                continue;
            }

            $lineAmount = round($line['hours'] * $line['rate'], 2);
            $amount += $lineAmount;
            $lines[] = [
                'description' => $line['description'],
                'hours' => $line['hours'],
                'rate' => $line['rate'],
                'amount' => $lineAmount,
                'minutes' => $block->billedMinutes,
                'entry_ids' => $block->entryIds,
                'project_name' => $block->project?->name,
            ];
        }

        $travelAmount = 0.0;
        foreach ($charges as $charge) {
            $travelAmount += $charge->amount();
        }

        return [
            'entries' => $entries,
            'lines' => $lines,
            'travel' => ['count' => count($charges), 'amount' => round($travelAmount, 2)],
            'totals' => [
                'count' => $entries->count(),
                'minutes' => (int) $entries->sum('minutes'),
                'amount' => round($amount + $travelAmount, 2),
            ],
            'warnings' => [
                'late_count' => app(LateTimeEntryDetector::class)->detect($entries->collect(), $customer, $project)->count(),
            ],
        ];
    }

    /**
     * Leere Pro-forma (Feature 066, MVP-171): eigener Nummernkreis (PF, ohne
     * steuerliche Belegwirkung), Positionen kommen manuell über den
     * Positions-Dialog. KEINE Quellposten — eine Pro-forma verbraucht nie
     * abrechenbare Zeiten/Material.
     */
    public function emptyProforma(Customer $customer, ?Project $project = null): Invoice {
        $this->assertLocalBillingAllowed($customer);

        return DB::transaction(function () use ($customer, $project): Invoice {
            $tax = app(TaxResolver::class)->resolve($customer->organization()->firstOrFail(), $customer);
            $notes = (string) __('Pro-forma-Rechnung — keine Rechnung im umsatzsteuerlichen Sinn.');
            if ($tax['note'] !== null) {
                $notes .= "\n" . $tax['note'];
            }

            return Invoice::create([
                'organization_id' => $customer->organization_id,
                'customer_id' => $customer->id,
                'project_id' => $project?->id,
                'number' => $this->numberSequence->next((int) $customer->organization_id, NumberScope::Proforma, now()),
                'status' => Invoice::STATUS_DRAFT,
                'type' => Invoice::TYPE_PROFORMA,
                'currency' => $customer->currency,
                'tax_rate' => $tax['rate'],
                'is_reverse_charge' => $tax['reverse_charge'],
                'notes' => $notes,
                'created_by' => Auth::id(),
            ]);
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
        $this->assertLocalBillingAllowed($customer);

        return DB::transaction(function () use ($customer, $project, $range, $foreignCustomer): Invoice {
            // Per-Kunde serialisieren (Doppelklick-Schutz gegen Doppelabrechnung).
            Customer::query()->whereKey($customer->id)->lockForUpdate()->first();

            // Quellposten UNTER Sperre und VOR Nummernvergabe laden: sonst
            // erzeugt ein zweiter Lauf eine leere Rechnung samt verbrauchter
            // Nummer bzw. würde dieselben Posten doppelt abrechnen.
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
                ->with([
                    'timesheet:id,work_date,project_id',
                    'timesheet.project:id,name,foreign_customer_id',
                    'timesheet.project.foreignCustomer:id,name,company',
                ])
                ->lockForUpdate()
                ->get();

            $charges = app(\App\Services\Travel\TravelChargeService::class)
                ->chargesForRange($customer, $project, $range, $foreignCustomer, true);

            if ($usages->isEmpty() && count($charges) === 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'customer_id' => (string) __('Für den gewählten Zeitraum gibt es keine abrechenbaren Material- oder Anfahrtsposten.'),
                ]);
            }

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
                'currency' => $customer->currency,
                'tax_rate' => ($materialTax = app(TaxResolver::class)->resolve($customer->organization()->firstOrFail(), $customer))['rate'],
                'is_reverse_charge' => $materialTax['reverse_charge'],
                'notes' => trim(($notes !== null ? $notes . "\n" : '') . ($materialTax['note'] ?? '')) ?: null,
                'created_by' => Auth::id(),
            ]);

            $position = 0;
            foreach ($usages as $usage) {
                if (($usage->line_total_net?->toFloat() ?? 0.0) <= 0 && ($usage->unit_price?->toFloat() ?? 0.0) <= 0) {
                    continue;
                }

                $materialDesc = trim((string) $usage->description) ?: (string) __('Material');

                $invoice->items()->create([
                    'material_usage_id' => $usage->id,
                    'service_date' => optional($usage->timesheet?->work_date)->toDateString(),
                    'description' => $this->bookingLine($materialDesc, $usage->timesheet?->project?->foreignCustomer),
                    'quantity' => $usage->quantity?->getNumericValue() ?? '0',
                    'unit' => $usage->unit ?: (string) __('invoicing.unit_piece'),
                    'unit_price' => $usage->unit_price?->getAmount() ?? '0',
                    'position' => ++$position,
                ]);

                $usage->billed = true;
                $usage->saveQuietly();
            }

            // Anfahrt nur für reine Materialtage (Leistungstage bleiben der
            // Leistungsrechnung vorbehalten). Charges wurden oben unter Sperre geladen.
            $this->appendTravelCharges($invoice, $charges, $foreignCustomer, $position);

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
    /** @param iterable<int, \App\Services\Travel\TravelCharge> $charges vorab (unter Sperre) geladen */
    private function appendTravelCharges(
        Invoice $invoice,
        iterable $charges,
        ?ForeignCustomer $foreignCustomer,
        int &$position,
    ): void {
        foreach ($charges as $charge) {
            $invoice->items()->create([
                'tour_id' => $charge->tour->id,
                'service_date' => $charge->date->toDateString(),
                'description' => $this->bookingLine($charge->description, $foreignCustomer),
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
     * Hoheits-Sperre (Feature 045, additiv): führt ein externes Programm die
     * Fakturierung des Kunden (lexoffice/datev), darf WorkDiary keine lokale
     * Rechnung aus Zeiten/Material erzeugen — die Quellen gehen stattdessen
     * als Übergabenachweis (BillingTransfer) an das führende System.
     *
     * @throws BillingModeLockedException
     */
    private function assertLocalBillingAllowed(Customer $customer): void {
        $mode = app(BillingModeResolver::class)->effectiveFor($customer);
        if ($mode->isExternal()) {
            throw new BillingModeLockedException($mode);
        }
    }

    /**
     * Kundenkonto-Guard (Feature 098, E5): führt der Kunde ein saldenbasiertes
     * Abrechnungsprofil (Konto- ODER Retainer-Modus), würde ein Zeiten-
     * Rechnungslauf dieselben Leistungen doppelt abrechnen (Saldo UND Beleg).
     * Zusätzlich schützt exported=true aus dem Monatsabschluss vor Altdaten-
     * Nebenpfaden. Die Retainer-Pauschale läuft über retainerChargeFor (kein
     * Zeitbezug) und ist davon nicht betroffen.
     */
    private function assertNotAccountManaged(Customer $customer): void {
        $agreement = $customer->billingAgreement;
        if ($agreement !== null && $agreement->keepsLedger()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'customer_id' => (string) __('customer-billing.account_mode_invoice_blocked'),
            ]);
        }
    }

    /**
     * Stellt der Buchungszeile (Positionsbeschreibung) den abgerechneten
     * Endkunden (Fremdkunden) voran, sofern vorhanden. So ist auf der Rechnung
     * je Position erkennbar, für welchen Endkunden abgerechnet wird — auch wenn
     * eine Rechnung mehrere Endkunden des Kunden zusammenfasst.
     */
    private function bookingLine(string $description, ?ForeignCustomer $foreignCustomer): string {
        if ($foreignCustomer === null) {
            return $description;
        }
        $name = trim((string) ($foreignCustomer->company ?: $foreignCustomer->name));
        if ($name === '') {
            return $description;
        }

        return (string) __('Endkunde :name', ['name' => $name]) . ' · ' . $description;
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
     * Stornorechnung (Feature 066, MVP-162/172): eigener Beleg im
     * Nummernkreis S mit vollständig negierten Positionen und dem
     * Steuerkontext des Originals; das Original wird als storniert
     * markiert. Nur für ausgestellte, unbezahlte Rechnungen.
     */
    public function cancellationFor(Invoice $original, ?string $reason = null, ?int $userId = null): Invoice {
        if ($original->status !== Invoice::STATUS_ISSUED || $original->isCreditNote()) {
            throw new \LogicException('Only issued invoices can be reversed (status: ' . $original->status . ')');
        }

        return DB::transaction(function () use ($original, $reason, $userId): Invoice {
            $original->loadMissing('items');

            $cancellation = Invoice::create([
                'organization_id' => $original->organization_id,
                'customer_id' => $original->customer_id,
                'project_id' => $original->project_id,
                'number' => $this->numberSequence->next((int) $original->organization_id, \App\Enums\Numbering\NumberScope::Cancellation, now()),
                'status' => Invoice::STATUS_DRAFT,
                'type' => Invoice::TYPE_CANCELLATION,
                'category' => $original->category,
                'parent_invoice_id' => $original->id,
                'currency' => $original->currency,
                'tax_rate' => $original->tax_rate,
                'is_reverse_charge' => (bool) $original->is_reverse_charge,
                // MVP-416: Belegrabatt spiegeln (Prozent skaliert selbst, fester Betrag negiert) — sonst negiert die Summe nicht exakt.
                'discount_percent' => $original->discount_percent,
                'discount_amount' => $original->discount_amount?->negated(),
                'notes' => __('Stornorechnung zu Rechnung :nr vom :date', [
                    'nr' => $original->number,
                    'date' => optional($original->issued_on ?? $original->created_at)->format('d.m.Y'),
                ]),
                'created_by' => $userId ?? Auth::id(),
            ]);

            $position = 0;
            foreach ($original->items as $item) {
                $cancellation->items()->create([
                    'organization_id' => $original->organization_id,
                    'service_date' => $item->service_date?->toDateString(),
                    'description' => $item->description,
                    'quantity' => (string) (-1 * (float) $item->quantity),
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                    // MVP-416: Positionsrabatt spiegeln (Prozent skaliert, Betrag negiert).
                    'discount_percent' => $item->discount_percent,
                    'discount_amount' => $item->discount_amount?->negated(),
                    'tax_rate' => $item->tax_rate,
                    'position' => ++$position,
                ]);
            }

            $cancellation->load('items');
            $cancellation->recalculate();
            $cancellation->save();

            $original->cancel($reason ?? (string) __('Storniert durch Stornorechnung :nr', ['nr' => $cancellation->number]), $userId ?? (int) Auth::id());

            return $cancellation;
        });
    }

    /**
     * Abschlags-/Anzahlungsrechnung (Feature 066, Belegkette): Rechnung über
     * ein vor der Leistung vereinnahmtes Teilentgelt (§ 14 Abs. 5 UStG).
     * Normale Rechnungsnummer (Scope R), eine manuelle Pauschalposition;
     * die Anrechnung erfolgt später über {@see finalFromDraft()}.
     */
    public function downPaymentFor(
        Customer $customer,
        ?Project $project,
        string $description,
        string $netAmount,
        ?CarbonInterface $serviceDate = null,
    ): Invoice {
        $this->assertLocalBillingAllowed($customer);

        return DB::transaction(function () use ($customer, $project, $description, $netAmount, $serviceDate): Invoice {
            $tax = app(TaxResolver::class)->resolve(
                $customer->organization()->firstOrFail(),
                $customer,
                $serviceDate,
            );

            $notes = (string) __('Abschlags-/Anzahlungsrechnung über ein vor der Leistung vereinnahmtes Teilentgelt (§ 14 Abs. 5 UStG); die Anrechnung erfolgt in der Schlussrechnung.');
            if ($tax['note'] !== null) {
                $notes .= "\n" . $tax['note'];
            }

            $invoice = Invoice::create([
                'organization_id' => $customer->organization_id,
                'customer_id' => $customer->id,
                'project_id' => $project?->id,
                'number' => $this->nextNumber($customer->organization_id),
                'status' => Invoice::STATUS_DRAFT,
                'type' => Invoice::TYPE_DOWN_PAYMENT,
                'currency' => $customer->currency,
                'tax_rate' => $tax['rate'],
                'is_reverse_charge' => $tax['reverse_charge'],
                'notes' => $notes,
                'created_by' => Auth::id(),
            ]);

            $invoice->items()->create([
                'organization_id' => $customer->organization_id,
                'service_date' => $serviceDate?->toDateString(),
                'description' => $description,
                'quantity' => '1',
                'unit' => (string) __('invoicing.unit_flat'),
                'unit_price' => $netAmount,
                'tax_category' => $tax['category'],
                'position' => 1,
            ]);

            $invoice->load('items');
            $invoice->recalculate();
            $invoice->save();

            return $invoice;
        });
    }

    /**
     * Pauschal-/Ausgleichsbeleg für die externe Übergabe an das führende
     * Buchhaltungsprogramm (Feature 098, Retainer-Modus mit Lexoffice-Hoheit).
     * Eine einzige custom-Position; überspringt BEWUSST assertLocalBillingAllowed,
     * weil die Rechnung sofort mit finalize=true an Lexoffice geht (Nummernkreis/
     * Festschreibung/Zahlung liegen dort). Die übergebene $placeholderNumber ist
     * transient — {@see \App\Plugins\Lexoffice\LexofficeInvoiceService::publish}
     * überschreibt sie mit der Lexoffice-Belegnummer. NICHT für lokale Faktura.
     */
    public function retainerChargeFor(
        Customer $customer,
        string $description,
        string $netAmount,
        string $placeholderNumber,
        string $type = Invoice::TYPE_RETAINER,
        ?CarbonInterface $serviceDate = null,
    ): Invoice {
        return DB::transaction(function () use ($customer, $description, $netAmount, $placeholderNumber, $type, $serviceDate): Invoice {
            $tax = app(TaxResolver::class)->resolve(
                $customer->organization()->firstOrFail(),
                $customer,
                $serviceDate,
            );

            $invoice = Invoice::create([
                'organization_id' => $customer->organization_id,
                'customer_id' => $customer->id,
                'number' => $placeholderNumber,
                'status' => Invoice::STATUS_DRAFT,
                'type' => $type,
                'currency' => $customer->currency,
                'tax_rate' => $tax['rate'],
                'is_reverse_charge' => $tax['reverse_charge'],
                'notes' => $tax['note'],
                'created_by' => Auth::id(),
            ]);

            $invoice->items()->create([
                'organization_id' => $customer->organization_id,
                'service_date' => $serviceDate?->toDateString(),
                'description' => $description,
                'quantity' => '1',
                'unit' => (string) __('invoicing.unit_flat'),
                'unit_price' => $netAmount,
                'tax_category' => $tax['category'],
                'position' => 1,
            ]);

            $invoice->load('items');
            $invoice->recalculate();
            $invoice->save();

            return $invoice;
        });
    }

    /**
     * Offene (noch in keiner nicht stornierten Schlussrechnung angerechnete)
     * Abschlagsrechnungen des Kunden. Projekt- und Währungskontext müssen
     * exakt passen, damit keine fremden Teilentgelte abgesetzt werden.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Invoice>
     */
    public function openDownPaymentsFor(Customer $customer, ?int $projectId, ?string $currency = null): \Illuminate\Database\Eloquent\Collection {
        return Invoice::query()
            ->where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id)
            ->where('type', Invoice::TYPE_DOWN_PAYMENT)
            ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_PAID])
            ->when($projectId !== null, fn($q) => $q->where('project_id', $projectId), fn($q) => $q->whereNull('project_id'))
            ->when($currency !== null, fn($q) => $q->where('currency', $currency))
            ->whereDoesntHave('settlementItems', fn($q) => $q->whereHas(
                'invoice',
                fn($iq) => $iq->where('status', '!=', Invoice::STATUS_CANCELLED),
            ))
            ->orderBy('issued_on')
            ->orderBy('id')
            ->get();
    }

    /**
     * Macht einen Standard-Rechnungsentwurf zur Schlussrechnung: alle offenen
     * Abschlagsrechnungen desselben Kunden-/Projekt-/Währungskontexts werden
     * als negative Absetzungspositionen je Steuersatz angerechnet
     * (§ 14 Abs. 5 S. 2 UStG — sonst droht doppelter Steuerausweis).
     *
     * Die Abschlagsrechnungen bleiben unverändert (Unveränderlichkeits-Guard);
     * die Verknüpfung lebt auf den Absetzungspositionen. Ein Storno der
     * Schlussrechnung öffnet die Abschläge dadurch automatisch wieder.
     */
    public function finalFromDraft(Invoice $draft): Invoice {
        if ($draft->status !== Invoice::STATUS_DRAFT || $draft->type !== Invoice::TYPE_INVOICE) {
            throw new \LogicException('Only draft standard invoices can become a final invoice (type: ' . $draft->type . ', status: ' . $draft->status . ')');
        }

        return DB::transaction(function () use ($draft): Invoice {
            // Per-Kunde serialisieren: zwei parallele Schlussrechnungen dürfen
            // dieselben Abschläge nicht doppelt absetzen.
            Customer::query()->whereKey($draft->customer_id)->lockForUpdate()->first();

            $downPayments = $this->openDownPaymentsFor(
                $draft->customer()->firstOrFail(),
                $draft->project_id,
                $draft->currency->value,
            );

            if ($downPayments->isEmpty()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'invoice' => (string) __('Es gibt keine offenen Abschlagsrechnungen dieses Kunden-/Projektkontexts zur Anrechnung.'),
                ]);
            }

            $draft->loadMissing('items');
            $position = (int) $draft->items->max('position');

            foreach ($downPayments as $dp) {
                // Absetzung je Steuersatz aus dem beim Ausstellen des
                // Abschlags eingefrorenen Aufriss — so bleibt die Steuer der
                // Schlussrechnung auch bei Satzwechseln centgenau konsistent.
                $rows = collect(is_array($dp->tax_breakdown) ? $dp->tax_breakdown : [])
                    ->filter(fn(array $row): bool => abs((float) ($row['net'] ?? 0)) > 0.0)
                    ->values();
                if ($rows->isEmpty()) {
                    $rows = collect([['rate' => $dp->tax_rate !== null ? (float) $dp->tax_rate->getNumericValue() : 0.0, 'net' => $dp->subtotal?->toFloat() ?? 0.0]]);
                }

                foreach ($rows as $row) {
                    $description = (string) __('abzüglich Abschlagsrechnung :nr vom :date', [
                        'nr' => $dp->number,
                        'date' => optional($dp->issued_on)->format('d.m.Y'),
                    ]);
                    if ($rows->count() > 1) {
                        $description .= sprintf(' (%s %%)', NumberHelper::toGermanFormat((float) $row['rate'], 2, withThousandsSeparator: true));
                    }

                    $draft->items()->create([
                        'organization_id' => $draft->organization_id,
                        'settled_invoice_id' => $dp->id,
                        'description' => $description,
                        'quantity' => '-1',
                        'unit' => (string) __('invoicing.unit_flat'),
                        'unit_price' => (string) $row['net'],
                        'tax_rate' => number_format((float) $row['rate'], 2, '.', ''),
                        'tax_category' => data_get($dp->tax_context, 'category'),
                        'position' => ++$position,
                    ]);
                }
            }

            $draft->load('items');
            $draft->recalculate();

            if ($draft->total !== null && $draft->total->isNegative()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'invoice' => (string) __('Die Anrechnung der Abschläge übersteigt den Rechnungsbetrag — die Schlussrechnung wäre negativ.'),
                ]);
            }

            $draft->type = Invoice::TYPE_FINAL;
            $existing = trim((string) $draft->notes);
            $draft->notes = trim(($existing !== '' ? $existing . "\n" : '') . (string) __('Schlussrechnung — angerechnete Abschlagsrechnungen: :list (§ 14 Abs. 5 UStG).', [
                'list' => $downPayments->pluck('number')->implode(', '),
            ]));
            $draft->save();

            return $draft;
        });
    }

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
                'tax_rate' => $original->tax_rate,
                // MVP-162/172: Steuerkontext des ORIGINALS übernehmen — sonst
                // droht unrichtiger Steuerausweis in der Korrektur (§ 14c).
                'is_reverse_charge' => (bool) $original->is_reverse_charge,
                // MVP-416: Belegrabatt spiegeln (Prozent skaliert selbst, fester Betrag negiert).
                'discount_percent' => $original->discount_percent,
                'discount_amount' => $original->discount_amount?->negated(),
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
                    'unit_price' => $item->unit_price,
                    // MVP-416: Positionsrabatt spiegeln (Prozent skaliert, Betrag negiert).
                    'discount_percent' => $item->discount_percent,
                    'discount_amount' => $item->discount_amount?->negated(),
                    'tax_rate' => $item->tax_rate,
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
