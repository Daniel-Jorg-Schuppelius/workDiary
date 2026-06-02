<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseInvoicingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Expense;

use App\Enums\Expense\ExpenseStatus;
use App\Models\{Expense, Invoice};
use Illuminate\Database\Eloquent\{Builder, Collection};
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Verknüpft genehmigte, weiterberechenbare Spesen mit einer Rechnung.
 *
 * - Akzeptiert ausschließlich Spesen im Status {@see ExpenseStatus::Approved}
 *   mit `billable=true`, deren Kunde zur Rechnung passt (direkt über
 *   `customer_id` oder über den Kunden des verknüpften Projekts).
 * - Erzeugt pro Spese eine {@see \App\Models\InvoiceItem} mit gesetztem
 *   `expense_id` (Menge 1, Einzelpreis = Brutto). Position führt fort,
 *   wo die Rechnung aktuell endet.
 * - Setzt den Spesen-Status auf {@see ExpenseStatus::Invoiced} und ruft am
 *   Ende `Invoice::recalculate()` auf.
 */
class ExpenseInvoicingService {
    /**
     * Liefert eine Query über Spesen, die einer Rechnung hinzugefügt werden
     * dürfen. Filtert nach passendem Kunden und Status `Approved`.
     *
     * @return Builder<Expense>
     */
    public function availableForInvoice(Invoice $invoice): Builder {
        $customerId = $invoice->customer_id;

        $query = Expense::query()
            ->with(['category:id,label,icon,color', 'user:id,name'])
            ->where('billable', true)
            ->where('status', ExpenseStatus::Approved->value)
            ->where(function (Builder $q) use ($customerId): void {
                $q->where('customer_id', $customerId)
                    ->orWhere(function (Builder $q2) use ($customerId): void {
                        $q2->whereNull('customer_id')
                            ->whereHas('project', fn(Builder $p) => $p->where('customer_id', $customerId));
                    });
            });
        $query->orderBy('date');

        return $query;
    }

    /**
     * @param  Collection<int, Expense>  $expenses
     */
    public function addToInvoice(Invoice $invoice, Collection $expenses): Invoice {
        if ($invoice->status !== Invoice::STATUS_DRAFT) {
            throw new RuntimeException(__('Spesen können nur einem Rechnungsentwurf hinzugefügt werden.'));
        }

        return DB::transaction(function () use ($invoice, $expenses): Invoice {
            $invoice->loadMissing(['items', 'customer']);
            $position = (int) ($invoice->items->max('position') ?? 0);

            foreach ($expenses as $expense) {
                if ($expense->status !== ExpenseStatus::Approved) {
                    continue;
                }
                if (! $expense->billable) {
                    continue;
                }
                if (! $this->matchesCustomer($expense, (int) $invoice->customer_id)) {
                    continue;
                }
                if ($expense->invoiceItem()->exists()) {
                    continue; // bereits einer anderen Rechnung zugeordnet
                }

                $description = $this->describe($expense);

                $invoice->items()->create([
                    'expense_id' => $expense->id,
                    'service_date' => $expense->date?->toDateString(),
                    'description' => $description,
                    'quantity' => '1.00',
                    'unit' => (string) __('invoicing.unit_piece'),
                    'unit_price' => (string) $expense->amount_gross,
                    'position' => ++$position,
                ]);

                $expense->status = ExpenseStatus::Invoiced;
                $expense->saveQuietly();
            }

            $invoice->load('items');
            $invoice->recalculate();
            $invoice->save();

            return $invoice;
        });
    }

    private function matchesCustomer(Expense $expense, int $customerId): bool {
        if ((int) $expense->customer_id === $customerId) {
            return true;
        }
        if ($expense->customer_id === null && $expense->project_id !== null) {
            $expense->loadMissing('project');

            return $expense->project !== null && (int) $expense->project->customer_id === $customerId;
        }

        return false;
    }

    private function describe(Expense $expense): string {
        $expense->loadMissing('category');
        $parts = [];
        if ($expense->category !== null) {
            $parts[] = $expense->category->label;
        }
        if ($expense->vendor !== null && $expense->vendor !== '') {
            $parts[] = (string) $expense->vendor;
        }
        $parts[] = $expense->date->format('d.m.Y');

        $line = implode(' · ', $parts);
        if ((string) $expense->description !== '') {
            $line .= ' — ' . $expense->description;
        }

        return $line;
    }
}
