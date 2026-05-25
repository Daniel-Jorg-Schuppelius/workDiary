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

use App\Models\{Customer, Invoice, Project, TimeEntry};
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Auth, DB};

class InvoiceGenerator {
    /**
     * Liefert die nächste freie Rechnungsnummer für Jahr + Organisation.
     *
     * MUSS innerhalb einer DB-Transaktion mit `lockForUpdate()` aufgerufen werden,
     * damit parallele Generierungen nicht dieselbe Nummer vergeben
     * (Rückwirkungs-Schutz für die unique-Constraint (organization_id, number)).
     *
     * @param  string  $prefixLetter  'R' = Rechnung, 'G' = Gutschrift/Korrekturrechnung
     */
    public function nextNumber(?int $organizationId, ?CarbonInterface $when = null, string $prefixLetter = 'R'): string {
        $when ??= Carbon::now();
        $year = (int) $when->format('Y');
        $prefix = sprintf('%s%d-', $prefixLetter, $year);

        /** @var string|null $last */
        $last = Invoice::query()
            ->where('organization_id', $organizationId)
            ->where('number', 'like', $prefix . '%')
            ->orderByDesc('number')
            ->lockForUpdate()
            ->value('number');

        $seq = 1;
        if ($last !== null && preg_match('/-(\d+)$/', $last, $m) === 1) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a draft invoice from billable, not-yet-exported time entries
     * for the given customer (and optionally project) within a date range.
     *
     * @param  array{from?: string|CarbonInterface|null, to?: string|CarbonInterface|null}  $range
     */
    public function fromTimeEntries(Customer $customer, ?Project $project, array $range = []): Invoice {
        return DB::transaction(function () use ($customer, $project, $range): Invoice {
            $invoice = Invoice::create([
                'organization_id' => $customer->organization_id,
                'customer_id' => $customer->id,
                'project_id' => $project?->id,
                'number' => $this->nextNumber($customer->organization_id),
                'status' => Invoice::STATUS_DRAFT,
                'currency' => $customer->currency ?: (string) setting('invoicing.default_currency', 'EUR'),
                'tax_rate' => (string) setting('invoicing.default_tax_rate', '19.00'),
                'created_by' => Auth::id(),
            ]);

            $query = TimeEntry::query()
                ->where('billable', true)
                ->where('exported', false)
                ->whereHas('project', fn($q) => $q->where('customer_id', $customer->id));

            if ($project !== null) {
                $query->where('project_id', $project->id);
            }
            if (! empty($range['from'])) {
                $query->where('date', '>=', Carbon::parse($range['from'])->toDateString());
            }
            if (! empty($range['to'])) {
                $query->where('date', '<=', Carbon::parse($range['to'])->toDateString());
            }

            $position = 0;
            foreach ($query->orderBy('date')->get() as $entry) {
                $hours = round(((int) $entry->minutes) / 60, 2);
                if ($hours <= 0) {
                    continue;
                }
                $rate = (float) ($entry->hourly_rate ?: $customer->hourly_rate ?: 0);
                $description = trim((string) ($entry->description ?: __('invoicing.service_on', ['date' => optional($entry->date)->format('d.m.Y')])));

                $invoice->items()->create([
                    'time_entry_id' => $entry->id,
                    'description' => $description,
                    'quantity' => (string) $hours,
                    'unit' => (string) __('invoicing.unit_hour'),
                    'unit_price' => (string) $rate,
                    'position' => ++$position,
                ]);
            }

            $invoice->load('items');
            $invoice->recalculate();
            $invoice->save();

            return $invoice;
        });
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
