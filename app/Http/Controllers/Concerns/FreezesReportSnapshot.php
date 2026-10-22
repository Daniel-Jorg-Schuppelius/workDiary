<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FreezesReportSnapshot.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;

/**
 * Berichtsstand einfrieren (Nachweis): eine Zeile mit Zeitraum, Kennzahlen
 * und Ersteller, eine Meldung für alle Auswertungen (Konsolidierungs-Audit
 * 2026-10-21, ko-17). Der Controller prüft das Recht und rechnet die Zahlen.
 */
trait FreezesReportSnapshot {
    /**
     * @param  class-string<Model>  $snapshotModel
     * @param  array<string, mixed>  $payload
     */
    private function freezeReportSnapshot(string $snapshotModel, CarbonInterface $from, CarbonInterface $to, array $payload, int $actorId): RedirectResponse {
        $snapshotModel::query()->create([
            'organization_id' => $this->currentOrganization()->id,
            'period_start' => $from->toDateString(),
            'period_end' => $to->toDateString(),
            'payload' => $payload,
            'created_by' => $actorId,
        ]);

        return back()->with('status', __('Berichtsstand eingefroren.'));
    }
}
