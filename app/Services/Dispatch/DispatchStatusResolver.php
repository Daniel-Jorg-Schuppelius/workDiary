<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DispatchStatusResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Dispatch;

use App\Enums\Diary\{DispatchStatus, Status};
use App\Models\DiaryEntry;
use Illuminate\Support\Facades\DB;

/**
 * Liefert und persistiert den Dispositionsstatus eines Auftrags, OHNE die
 * (WIP-) Modellklasse DiaryEntry anzufassen.
 *
 * Auflösungs-Reihenfolge:
 *  1. Explizit gesetzte Spalte diary_entries.dispatch_status (manuelle
 *     Disposition / bestätigte Übergänge) gewinnt.
 *  2. Sonst wird der Status aus den vorhandenen Planungsfeldern abgeleitet
 *     (Status / planned_at / assigned_user_id / Lifecycle-Zeitstempel),
 *     sodass Altdaten und nicht explizit disponierte Aufträge sinnvoll
 *     dargestellt werden.
 *
 * Schreibzugriff erfolgt bewusst über den Query-Builder (DB::table), damit
 * keine Eloquent-Events/Casts der WIP-Klasse berührt werden.
 */
final class DispatchStatusResolver {
    /** Effektiver Dispositionsstatus (Spalte bevorzugt, sonst abgeleitet). */
    public function resolve(DiaryEntry $entry): DispatchStatus {
        $stored = $entry->getAttribute('dispatch_status');
        if (is_string($stored) && $stored !== '') {
            $enum = DispatchStatus::tryFrom($stored);
            if ($enum !== null) {
                return $enum;
            }
        }

        return $this->derive($entry);
    }

    /** Reine Ableitung aus vorhandenen Feldern (ignoriert die Spalte). */
    public function derive(DiaryEntry $entry): DispatchStatus {
        $status = $entry->status;

        // Erledigt/abgeschlossen → Disposition erledigt.
        if (in_array($status, [Status::Done, Status::AcceptedFinal, Status::Invoiced], true)) {
            return DispatchStatus::Done;
        }
        if ($entry->getAttribute('completed_at') !== null) {
            return DispatchStatus::Done;
        }

        // In Arbeit / gestartet → unterwegs/aktiv.
        if ($status === Status::InProgress || $entry->getAttribute('started_at') !== null) {
            return DispatchStatus::EnRoute;
        }

        // Angenommen → bestätigte Disposition.
        if ($status === Status::Accepted || $entry->getAttribute('accepted_at') !== null) {
            return DispatchStatus::Confirmed;
        }

        // Terminiert/zugewiesen → geplant.
        $hasSchedule = $entry->planned_at !== null
            || $entry->start_at !== null
            || $entry->getAttribute('assigned_user_id') !== null;
        if ($hasSchedule) {
            return DispatchStatus::Planned;
        }

        return DispatchStatus::Unplanned;
    }

    /**
     * Setzt den Dispositionsstatus explizit (Übergang) und persistiert ihn
     * über den Query-Builder. Optionaler Override-Audit-Trail (bewusste
     * Übersteuerung harter Konflikte).
     */
    public function transition(
        DiaryEntry $entry,
        DispatchStatus $target,
        ?int $overrideByUserId = null,
        ?string $overrideReason = null,
    ): DispatchStatus {
        $update = ['dispatch_status' => $target->value];

        if ($target === DispatchStatus::Confirmed) {
            $update['dispatch_confirmed_at'] = now();
        }
        if ($overrideReason !== null && $overrideReason !== '') {
            $update['dispatch_override_reason'] = $overrideReason;
            $update['dispatch_override_by_user_id'] = $overrideByUserId;
        }

        DB::table('diary_entries')->where('id', $entry->getKey())->update($update);

        // In-Memory aktuell halten, ohne Eloquent-Events der WIP-Klasse.
        $entry->setAttribute('dispatch_status', $target->value);
        foreach ($update as $key => $value) {
            $entry->setAttribute($key, $value);
        }

        return $target;
    }
}
