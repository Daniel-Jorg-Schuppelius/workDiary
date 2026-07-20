<?php
/*
 * Created on   : Sat Jul 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LoadsBillingSources.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Targets\Concerns;

use App\Models\Finance\BillingTransfer;
use App\Models\{MaterialUsage, TimeEntry};
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

/**
 * Gemeinsames Quell-Lade-Skelett der Facturation-Targets (Vollaudit 2026-07,
 * M41): TimeEntry-/MaterialUsage-Ladung inkl. Vollständigkeits-Guard
 * (sources_missing) war wortgleich in SevDesk/Easybill/OrgaMax/Lexoffice
 * kopiert. Aggregation und provider-spezifische Positions-Arrays bleiben in
 * den Targets.
 */
trait LoadsBillingSources {
    /**
     * Alle Zeiteinträge des Transfers, sortiert nach Datum; wirft
     * sources_missing, wenn Quellen zwischenzeitlich fehlen.
     *
     * @return Collection<int, TimeEntry>
     */
    private function loadTimeEntries(BillingTransfer $transfer): Collection {
        $ids = $transfer->items
            ->where('source_type', TimeEntry::class)
            ->pluck('source_id')
            ->all();

        $entries = TimeEntry::query()
            ->whereIn('id', $ids)
            ->with(['project.parent', 'project.customer', 'project.foreignCustomer'])
            ->orderBy('date')
            ->get();
        if ($entries->count() !== count($ids)) {
            throw new RuntimeException((string) __('finance.error.sources_missing'));
        }

        return $entries;
    }

    /**
     * Alle Materialverwendungen des Transfers; wirft sources_missing, wenn
     * Quellen zwischenzeitlich fehlen.
     *
     * @return Collection<int, MaterialUsage>
     */
    private function loadMaterialUsages(BillingTransfer $transfer): Collection {
        $ids = $transfer->items
            ->where('source_type', MaterialUsage::class)
            ->pluck('source_id')
            ->all();

        $usages = MaterialUsage::query()
            ->whereIn('id', $ids)
            ->with(['timesheet:id,work_date,project_id', 'timesheet.project:id,name'])
            ->get();
        if ($usages->count() !== count($ids)) {
            throw new RuntimeException((string) __('finance.error.sources_missing'));
        }

        return $usages;
    }
}
