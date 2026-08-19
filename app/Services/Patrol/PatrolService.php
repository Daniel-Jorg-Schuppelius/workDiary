<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PatrolService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Patrol;

use App\Enums\OpenIssue\OpenIssueSource;
use App\Models\{OpenIssue, User};
use App\Models\Patrol\{PatrolCheckpoint, PatrolRoute, PatrolRun};
use Illuminate\Support\{Carbon, Str};
use RuntimeException;

/**
 * Rundgangs-Durchführung (Feature 089, MVP-663–665).
 *
 * Die Soll-Zeiten sind **Nachweis-, keine Leistungsdruck-Metrik**: Der Scan
 * belegt Punkt und Zeit; die Abweichung wird ausgewiesen und begründet, nie
 * geglättet. Der Alarm läuft über das vorhandene Offene-Punkte-System — ein
 * verpasster Kontrollpunkt IST ein offener Punkt am Objekt, kein eigener
 * Benachrichtigungskanal.
 */
class PatrolService {
    /**
     * Kontrollpunkt anlegen; der Klartext-Token wird genau EINMAL
     * zurückgegeben (Muster Prüfer-Link).
     *
     * @return array{checkpoint: PatrolCheckpoint, token: string}
     */
    public function addCheckpoint(PatrolRoute $route, string $label, int $offsetMinutes, int $toleranceMinutes = 10): array {
        $token = Str::upper(Str::random(12));
        $checkpoint = $route->checkpoints()->create([
            'organization_id' => $route->organization_id,
            'position' => (int) $route->checkpoints()->max('position') + 1,
            'label' => $label,
            'token_hash' => PatrolCheckpoint::hashToken($token),
            'token_suffix' => mb_substr($token, -4),
            'expected_offset_minutes' => max(0, $offsetMinutes),
            'tolerance_minutes' => max(0, $toleranceMinutes),
        ]);

        return ['checkpoint' => $checkpoint, 'token' => $token];
    }

    /**
     * Verlorener Tag: neuer Token, gleiche Route — der alte ist damit sofort
     * wertlos (Hash ersetzt).
     *
     * @return array{checkpoint: PatrolCheckpoint, token: string}
     */
    public function reissueToken(PatrolCheckpoint $checkpoint): array {
        $token = Str::upper(Str::random(12));
        $checkpoint->forceFill([
            'token_hash' => PatrolCheckpoint::hashToken($token),
            'token_suffix' => mb_substr($token, -4),
        ])->save();

        return ['checkpoint' => $checkpoint, 'token' => $token];
    }

    public function start(PatrolRoute $route, User $actor): PatrolRun {
        if (! $route->active) {
            throw new RuntimeException((string) __('Diese Route ist nicht aktiv.'));
        }
        if ($route->checkpoints()->count() === 0) {
            throw new RuntimeException((string) __('Ohne Kontrollpunkte kein Rundgang.'));
        }
        if ($route->runs()->where('status', PatrolRun::STATUS_RUNNING)->exists()) {
            throw new RuntimeException((string) __('Für diese Route läuft bereits ein Rundgang.'));
        }

        $run = $route->runs()->create([
            'organization_id' => $route->organization_id,
            'started_by' => $actor->id,
            'status' => PatrolRun::STATUS_RUNNING,
            'started_at' => Carbon::now(),
        ]);
        $run->audit('patrol.started', ['route' => $route->name]);

        return $run;
    }

    /**
     * Scan eines Kontrollpunkts: löst den Token gegen die Route auf und
     * bewertet das Soll-Fenster. Doppelscans desselben Punkts sind
     * idempotent (der erste zählt).
     */
    public function scan(PatrolRun $run, string $token): PatrolCheckpoint {
        if ($run->status !== PatrolRun::STATUS_RUNNING) {
            throw new RuntimeException((string) __('Dieser Rundgang läuft nicht mehr.'));
        }

        $checkpoint = PatrolCheckpoint::query()
            ->where('patrol_route_id', $run->patrol_route_id)
            ->where('token_hash', PatrolCheckpoint::hashToken($token))
            ->first();
        if ($checkpoint === null) {
            throw new RuntimeException((string) __('Unbekannter Kontrollpunkt — der Token gehört nicht zu dieser Route.'));
        }

        if ($run->scans()->where('patrol_checkpoint_id', $checkpoint->id)->exists()) {
            return $checkpoint;
        }

        $now = Carbon::now();
        $elapsed = (int) $run->started_at->diffInMinutes($now);
        $delta = $elapsed - $checkpoint->expected_offset_minutes;
        $inWindow = abs($delta) <= $checkpoint->tolerance_minutes;

        $run->scans()->create([
            'organization_id' => $run->organization_id,
            'patrol_checkpoint_id' => $checkpoint->id,
            'scanned_at' => $now,
            'delta_minutes' => $delta,
            'in_window' => $inWindow,
        ]);

        return $checkpoint;
    }

    /**
     * Abschluss: Bei Abweichungen (verpasste Punkte oder Scans außerhalb des
     * Fensters) ist die Begründung Pflicht, und die Leitstelle bekommt einen
     * offenen Punkt am Rundgang — über das vorhandene Eskalationssystem.
     */
    public function complete(PatrolRun $run, User $actor, ?string $deviationNote = null): PatrolRun {
        if ($run->status !== PatrolRun::STATUS_RUNNING) {
            throw new RuntimeException((string) __('Dieser Rundgang läuft nicht mehr.'));
        }

        $missed = $this->missedCheckpoints($run);
        $late = $run->scans()->where('in_window', false)->count();

        if (($missed->isNotEmpty() || $late > 0) && blank($deviationNote)) {
            throw new RuntimeException((string) __('Abweichungen brauchen eine Begründung (:missed verpasst, :late außerhalb des Fensters).', [
                'missed' => $missed->count(),
                'late' => $late,
            ]));
        }

        $run->forceFill([
            'status' => PatrolRun::STATUS_COMPLETED,
            'finished_at' => Carbon::now(),
            'deviation_note' => $deviationNote,
        ])->save();
        $run->audit('patrol.completed', ['missed' => $missed->count(), 'late' => $late]);
        $this->writeLogbookEntry($run, $actor, $missed->count(), $late);

        if ($missed->isNotEmpty() || $late > 0) {
            OpenIssue::query()->create([
                'organization_id' => $run->organization_id,
                'subject_type' => $run->getMorphClass(),
                'subject_id' => $run->id,
                'source_type' => OpenIssueSource::PatrolDeviation->value,
                'title' => (string) __('Rundgang „:route": :missed Kontrollpunkte verpasst, :late außerhalb des Fensters', [
                    'route' => (string) $run->route?->name,
                    'missed' => $missed->count(),
                    'late' => $late,
                ]),
                'description' => $deviationNote,
                'severity' => 'high',
                'status' => 'open',
                'assignee_user_id' => $actor->id,
                'created_by_user_id' => $actor->id,
                'due_at' => Carbon::now()->addDay(),
            ]);
        }

        return $run;
    }

    /**
     * Wachbuch-Anbindung (Folgepunkt aus MVP-664): Der abgeschlossene
     * Rundgang schreibt sich selbst ins Auftragsbuch — aber nur, wenn das
     * Branchenprofil den Eintragstyp `revierfahrt` mitgebracht hat. Ohne
     * Profil kein Eintrag: Ein Wachbuch, das es fachlich nicht gibt, wird
     * nicht stillschweigend erfunden.
     */
    private function writeLogbookEntry(PatrolRun $run, User $actor, int $missed, int $late): void {
        $entryType = \App\Models\EntryType::query()
            ->where('slug', 'revierfahrt')
            ->first();
        if ($entryType === null) {
            return;
        }

        $entry = new \App\Models\DiaryEntry;
        $entry->organization_id = (int) $run->organization_id;
        $entry->user_id = (int) ($run->started_by ?? $actor->id);
        $entry->entry_type_id = $entryType->id;
        $entry->title = (string) __('Rundgang: :route', ['route' => (string) $run->route?->name]);
        $entry->content = trim((string) __(':scanned Kontrollpunkte bestätigt, :missed verpasst, :late außerhalb des Fensters.', [
            'scanned' => $run->scans()->count(),
            'missed' => $missed,
            'late' => $late,
        ]) . ($run->deviation_note ? ' ' . $run->deviation_note : ''));
        $entry->status = \App\Enums\Diary\Status::Done;
        $entry->start_at = $run->started_at;
        $entry->end_at = $run->finished_at;
        $entry->save();
    }

    /**
     * Kontrollpunkte ohne Scan in diesem Lauf.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, PatrolCheckpoint>
     */
    public function missedCheckpoints(PatrolRun $run): \Illuminate\Database\Eloquent\Collection {
        return PatrolCheckpoint::query()
            ->where('patrol_route_id', $run->patrol_route_id)
            ->whereNotIn('id', $run->scans()->pluck('patrol_checkpoint_id'))
            ->orderBy('position')
            ->get();
    }
}
