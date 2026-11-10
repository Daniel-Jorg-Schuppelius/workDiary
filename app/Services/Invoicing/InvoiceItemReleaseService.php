<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceItemReleaseService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Models\Diary\Tour;
use App\Models\Invoicing\{Invoice, InvoiceItem, OnlinePayment};
use App\Models\Material\MaterialUsage;
use App\Models\Time\TimeEntry;
use App\Models\Travel\Expense;

/**
 * Quellposten einer Rechnungsposition wieder freigeben (Vollscan 2026-08-23,
 * F14 — vorher ein deleting-Hook, der in 5 Fremdtabellen schrieb): Spese →
 * Approved, Zeiten → exported=false, Material → billed=false, Tour →
 * travel_billed=false, Mietposten → Released, Auffüllung auf Soll → offen,
 * Auslieferung → Pending. Explizit aufrufbar — bei Query-Deletes/Kaskaden
 * feuert kein Eloquent-Event, dann MUSS der Aufrufer diese Freigabe selbst
 * anstoßen.
 */
class InvoiceItemReleaseService {
    /**
     * Storno der ganzen Rechnung, direkt oder per Stornobeleg (MVP-1521): gibt
     * jede Quelle frei wie der Storno einer Übergabe. Die Positionen bleiben
     * unverändert am Beleg (GoBD), nur die Quellen werden wieder abrechenbar.
     */
    public function releaseInvoice(Invoice $invoice): void {
        foreach ($invoice->items()->get() as $item) {
            $this->releaseSources($item);
        }
        $this->releasePrepayments($invoice);
    }

    /** Online-Vorauszahlungen (MVP-1515) gehören ihrem Gegenstand und gehen an ihn zurück. */
    public function releasePrepayments(Invoice $invoice): int {
        return OnlinePayment::query()->withoutGlobalScopes()
            ->where('invoice_id', $invoice->id)
            ->whereNotNull('subject_type')
            ->update(['invoice_id' => null]);
    }

    /** Muss VOR dem Löschen laufen: danach ist die Pivot-Zuordnung weg. */
    public function releaseSources(InvoiceItem $i): void {
        if ($i->expense_id !== null) {
            $expense = Expense::query()->find($i->expense_id);
            if ($expense !== null && $expense->status === \App\Enums\Expense\ExpenseStatus::Invoiced) {
                $expense->status = \App\Enums\Expense\ExpenseStatus::Approved;
                $expense->saveQuietly();
            }
        }

        $entryIds = $i->timeEntries()->pluck('time_entries.id')->all();
        if ($i->time_entry_id !== null) {
            $entryIds[] = $i->time_entry_id;
        }
        if ($entryIds !== []) {
            TimeEntry::query()->whereKey(array_unique($entryIds))->update(['exported' => false]);
        }
        if ($i->material_usage_id !== null) {
            MaterialUsage::query()->whereKey($i->material_usage_id)->update(['billed' => false]);
        }
        if ($i->tour_id !== null) {
            Tour::query()->whereKey($i->tour_id)->update(['travel_billed' => false]);
        }
        app(TargetTopUps::class)->releaseInvoiceItems([(int) $i->id]);
        if ($i->stock_delivery_id !== null) {
            // Feature 160 (MVP-858): aktive Reservierung der Auslieferung lösen; die Herkunft bleibt am Posten.
            app(DeliveryInvoicingService::class)->release($i);
        }
        if ($i->rental_charge_id !== null) {
            $charge = \App\Models\Rental\RentalCharge::query()->find($i->rental_charge_id);
            if ($charge !== null && $charge->status === \App\Enums\Rental\RentalChargeStatus::Invoiced) {
                $charge->forceFill([
                    'status' => \App\Enums\Rental\RentalChargeStatus::Released->value,
                    'invoice_id' => null,
                    'invoiced_at' => null,
                ])->saveQuietly();
            }
        }
    }
}
