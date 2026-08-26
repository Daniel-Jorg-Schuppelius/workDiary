<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GobdExportJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Jobs\Finance;

use App\Enums\Finance\GobdExportStatus;
use App\Jobs\Concerns\RetriesTransientFailures;
use App\Models\{GobdExport, Organization, User};
use App\Services\Finance\GdpduExportService;
use App\Support\OrganizationContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\{ShouldBeUniqueUntilProcessing, ShouldQueue};
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Erzeugt das GoBD-Z3-Paket in der Queue (Vollscan 2026-08-23, A16, MVP-722).
 *
 * Vorher lief der ganze Aufbau im HTTP-Request: 18 Datenbereiche komplett in
 * den Speicher, dann das ZIP dazu, dann die Antwort — bei einem Jahrespaket
 * ein PHP-Timeout oder das Speicherlimit, und der Prüfer bekam nichts.
 *
 * Je Nachweiszeile genau ein wartender Job (ShouldBeUniqueUntilProcessing);
 * ein Doppelklick auf „Exportieren" legt zwar zwei Nachweise an, aber kein
 * Nachweis wird zweimal gebaut. Laufzeitbudget 5 min (RetriesTransientFailures,
 * timeout 300 s) — deutlich unter dem retry_after 630 s der DB-Queue, damit
 * kein zweiter Worker denselben Lauf anfasst.
 *
 * Der reproduzierbare Paket-Hash bleibt unberührt: er entsteht weiter über die
 * Dateiinhalte, nicht über den Ausführungsweg.
 */
class GobdExportJob implements ShouldBeUniqueUntilProcessing, ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use RetriesTransientFailures {
        failed as protected reportTransientFailure;
    }

    use SerializesModels;

    public function __construct(public readonly int $exportId) {
        $this->afterCommit();
    }

    public function uniqueId(): string {
        return (string) $this->exportId;
    }

    public function handle(GdpduExportService $exports): void {
        $export = GobdExport::query()->withoutGlobalScopes()->find($this->exportId);
        if (! $export instanceof GobdExport || ! $export->status->isPending()) {
            return;
        }

        $organization = $export->organization;
        if (! $organization instanceof Organization) {
            return;
        }

        $export->forceFill(['status' => GobdExportStatus::Running, 'started_at' => now()])->save();

        $actor = $export->created_by === null
            ? null
            : User::query()->withoutGlobalScopes()->find($export->created_by);

        // Die Bereiche fragen org-gescopte Modelle ab — ohne gesetzten
        // Organisationskontext liefe der Lauf gegen die falsche Mandantensicht.
        OrganizationContext::run($organization, fn (): array => $exports->build(
            $organization,
            Carbon::instance($export->period_from),
            Carbon::instance($export->period_to),
            array_values(array_filter((array) $export->sections, 'is_string')),
            $actor instanceof User ? $actor : null,
            (string) $export->encoding,
            $export,
        ));
    }

    /** Der Nachweis darf nicht als „läuft" hängen bleiben. */
    public function failed(?Throwable $e): void {
        $this->reportTransientFailure($e);

        GobdExport::query()->withoutGlobalScopes()->where('id', $this->exportId)->update([
            'status' => GobdExportStatus::Failed->value,
            'error' => mb_substr($e?->getMessage() ?? '', 0, 500),
            'finished_at' => now(),
        ]);
    }
}
