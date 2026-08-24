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

use App\Models\{Expense, InvoiceItem, MaterialUsage, TimeEntry, Tour};

/**
 * Quellposten einer Rechnungsposition wieder freigeben (Vollscan 2026-08-23,
 * F14 — vorher ein deleting-Hook, der in 5 Fremdtabellen schrieb): Spese →
 * Approved, Zeiten → exported=false, Material → billed=false, Tour →
 * travel_billed=false, Mietposten → Released. Explizit aufrufbar — bei
 * Query-Deletes/Kaskaden feuert kein Eloquent-Event, dann MUSS der Aufrufer
 * diese Freigabe selbst anstoßen.
 */
class InvoiceItemReleaseService {
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
