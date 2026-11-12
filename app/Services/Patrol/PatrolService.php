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

use App\Enums\OpenIssue\{OpenIssueSource, OpenIssueStatus};
use App\Enums\Patrol\PatrolRunStatus;
use App\Models\Diary\OpenIssue;
use App\Models\Patrol\{PatrolCheckpoint, PatrolRoute, PatrolRun};
use App\Models\Platform\{Organization, User};
use App\Support\CarbonFmt;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\{Carbon, Str};
use RuntimeException;

/**
 * Rundgangs-Durchführung (Feature 089, MVP-663–665, MVP-1611).
 *
 * Die Soll-Zeiten sind **Nachweis-, keine Leistungsdruck-Metrik**: Der Scan
 * belegt Punkt und Zeit; die Abweichung wird ausgewiesen und begründet, nie
 * geglättet. Der Alarm läuft über das vorhandene Offene-Punkte-System — ein
 * verpasster Kontrollpunkt IST ein offener Punkt am Objekt, kein eigener
 * Benachrichtigungskanal. Je Lauf gibt es höchstens einen offenen Punkt; er
 * entsteht bei der ersten Abweichung und wird bis zum Ende fortgeschrieben.
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
        if ($checkpoint->deactivated_at !== null) {
            throw new RuntimeException((string) __('patrol.checkpoint.deactivated'));
        }
        $token = Str::upper(Str::random(12));
        $checkpoint->forceFill([
            'token_hash' => PatrolCheckpoint::hashToken($token),
            'token_suffix' => mb_substr($token, -4),
        ])->save();

        return ['checkpoint' => $checkpoint, 'token' => $token];
    }

    /**
     * Kontrollpunkt ändern. Bereits erfasste Scans behalten ihre Bewertung —
     * sie wurde beim Scan festgehalten.
     */
    public function updateCheckpoint(PatrolCheckpoint $checkpoint, string $label, int $offsetMinutes, int $toleranceMinutes): PatrolCheckpoint {
        if ($checkpoint->deactivated_at !== null) {
            throw new RuntimeException((string) __('patrol.checkpoint.deactivated'));
        }
        $checkpoint->forceFill([
            'label' => $label,
            'expected_offset_minutes' => max(0, $offsetMinutes),
            'tolerance_minutes' => max(0, $toleranceMinutes),
        ])->save();
        $checkpoint->route?->audit('patrol.checkpoint_updated', ['checkpoint' => $checkpoint->label]);

        return $checkpoint;
    }

    /**
     * Kontrollpunkt deaktivieren: ab jetzt nicht mehr erwartet und nicht mehr
     * scanbar. Läufe, die vorher begonnen haben, zählen ihn weiter.
     */
    public function deactivateCheckpoint(PatrolCheckpoint $checkpoint): PatrolCheckpoint {
        if ($checkpoint->deactivated_at === null) {
            $checkpoint->forceFill(['deactivated_at' => Carbon::now()])->save();
            $checkpoint->route?->audit('patrol.checkpoint_deactivated', ['checkpoint' => $checkpoint->label]);
        }

        return $checkpoint;
    }

    /** @param array{name: string, site_id: ?int, responsible_user_id: ?int} $attributes */
    public function updateRoute(PatrolRoute $route, array $attributes): PatrolRoute {
        $route->fill($attributes)->save();
        if ($route->wasChanged()) {
            $route->audit('patrol.route_updated', ['fields' => array_keys($route->getChanges())]);
        }

        return $route;
    }

    /** Route deaktivieren oder wieder freigeben; ein laufender Rundgang darf zu Ende gehen. */
    public function setRouteActive(PatrolRoute $route, bool $active): PatrolRoute {
        if ($route->active !== $active) {
            $route->forceFill(['active' => $active])->save();
            $route->audit($active ? 'patrol.route_activated' : 'patrol.route_deactivated', []);
        }

        return $route;
    }

    public function start(PatrolRoute $route, User $actor): PatrolRun {
        if (! $route->active) {
            throw new RuntimeException((string) __('Diese Route ist nicht aktiv.'));
        }
        if (! $route->checkpoints()->whereNull('deactivated_at')->exists()) {
            throw new RuntimeException((string) __('Ohne Kontrollpunkte kein Rundgang.'));
        }
        if ($route->runs()->where('status', PatrolRunStatus::Running)->exists()) {
            throw new RuntimeException((string) __('Für diese Route läuft bereits ein Rundgang.'));
        }

        $run = $route->runs()->create([
            'organization_id' => $route->organization_id,
            'started_by' => $actor->id,
            'status' => PatrolRunStatus::Running,
            'started_at' => Carbon::now(),
        ]);
        $run->audit('patrol.started', ['route' => $route->name]);

        return $run;
    }

    /**
     * Scan eines Kontrollpunkts: löst den Token gegen die Route auf und
     * bewertet das Soll-Fenster. Doppelscans desselben Punkts sind
     * idempotent (der erste zählt). Ein Scan außerhalb des Fensters oder ein
     * übersprungener, schon fälliger Punkt meldet die Abweichung sofort.
     *
     * $at: Scanzeit des Geräts bei offline erfassten Scans (MVP-1291).
     */
    public function scan(PatrolRun $run, string $token, ?CarbonInterface $at = null): PatrolCheckpoint {
        if ($run->status !== PatrolRunStatus::Running) {
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
        if (! $checkpoint->isExpectedAt($run->started_at)) {
            throw new RuntimeException((string) __('patrol.checkpoint.deactivated'));
        }

        $now = Carbon::now();
        $scannedAt = $at !== null ? Carbon::instance($at) : $now;
        // Gerätezeit nur innerhalb des Laufs, mit zwei Minuten Uhrenabweichung.
        if ($scannedAt->lt($run->started_at->copy()->subMinutes(2)) || $scannedAt->gt($now->copy()->addMinutes(2))) {
            throw new RuntimeException((string) __('patrol.scan.implausibleTime'));
        }
        $scannedAt = $scannedAt->max($run->started_at);
        $elapsed = (int) $run->started_at->diffInMinutes($scannedAt);
        $delta = $elapsed - $checkpoint->expected_offset_minutes;
        $inWindow = abs($delta) <= $checkpoint->tolerance_minutes;

        $run->scans()->create([
            'organization_id' => $run->organization_id,
            'patrol_checkpoint_id' => $checkpoint->id,
            'scanned_at' => $scannedAt,
            'delta_minutes' => $delta,
            'in_window' => $inWindow,
        ]);

        $passed = $this->missedCheckpoints($run)
            ->filter(fn (PatrolCheckpoint $open): bool => $open->expected_offset_minutes + $open->tolerance_minutes < $elapsed);
        if (! $inWindow || $passed->isNotEmpty()) {
            $this->raiseDeviation($run, null, (string) __('patrol.deviation.running_title', ['route' => (string) $run->route?->name]), $this->deviationLines($run, $passed->count()));
        }

        return $checkpoint;
    }

    /**
     * Abschluss: Bei Abweichungen (verpasste Punkte oder Scans außerhalb des
     * Fensters) ist die Begründung Pflicht, und die Verantwortliche bekommt den
     * offenen Punkt am Rundgang — über das vorhandene Eskalationssystem.
     */
    public function complete(PatrolRun $run, User $actor, ?string $deviationNote = null): PatrolRun {
        if (! $run->status->canTransitionTo(PatrolRunStatus::Completed)) {
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
            'status' => PatrolRunStatus::Completed,
            'finished_at' => Carbon::now(),
            'deviation_note' => $deviationNote,
        ])->save();
        $run->audit('patrol.completed', ['missed' => $missed->count(), 'late' => $late]);
        $this->writeLogbookEntry($run, $actor, $missed->count(), $late);

        if ($missed->isNotEmpty() || $late > 0) {
            $this->raiseDeviation($run, $actor, (string) __('Rundgang „:route": :missed Kontrollpunkte verpasst, :late außerhalb des Fensters', [
                'route' => (string) $run->route?->name,
                'missed' => $missed->count(),
                'late' => $late,
            ]), $deviationNote);
        }

        return $run;
    }

    /**
     * Abbruch eines laufenden Rundgangs, immer mit Begründung. Die bis dahin
     * erfassten Scans bleiben als Nachweis stehen; ins Wachbuch kommt der Lauf
     * nicht (dort stehen durchgeführte Rundgänge). Offene oder verspätete
     * Kontrollpunkte gehen wie beim Abschluss als offener Punkt an die
     * Verantwortliche — sonst ließe sich die Eskalation durch Abbrechen umgehen.
     */
    public function abort(PatrolRun $run, User $actor, string $reason): PatrolRun {
        if (! $run->status->canTransitionTo(PatrolRunStatus::Aborted)) {
            throw new RuntimeException((string) __('Dieser Rundgang läuft nicht mehr.'));
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException((string) __('Der Abbruch braucht eine Begründung.'));
        }

        $missed = $this->missedCheckpoints($run);
        $late = $run->scans()->where('in_window', false)->count();

        $run->forceFill([
            'status' => PatrolRunStatus::Aborted,
            'finished_at' => Carbon::now(),
            'abort_reason' => $reason,
            'aborted_by_user_id' => $actor->id,
        ])->save();
        $run->audit('patrol.aborted', ['reason' => $reason, 'missed' => $missed->count(), 'late' => $late]);

        if ($missed->isNotEmpty() || $late > 0) {
            $this->raiseDeviation($run, $actor, (string) __('Rundgang „:route" abgebrochen: :missed Kontrollpunkte offen, :late außerhalb des Fensters', [
                'route' => (string) $run->route?->name,
                'missed' => $missed->count(),
                'late' => $late,
            ]), $reason);
        }

        return $run;
    }

    /**
     * Fristenlauf (MVP-1611): Ein Rundgang, der nach Soll-Dauer plus Toleranz
     * des letzten Kontrollpunkts noch läuft, meldet sich einmal als offener
     * Punkt. Abbrechen kann ihn danach die Leitstelle.
     */
    public function flagOverdueRuns(Organization $organization): int {
        $flagged = 0;
        $runs = PatrolRun::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('status', PatrolRunStatus::Running->value)
            ->whereNull('overdue_flagged_at')
            ->with('route')
            ->get();

        foreach ($runs as $run) {
            $planned = $this->plannedMinutes($run);
            if ($planned === null || $run->started_at->copy()->addMinutes($planned)->isFuture()) {
                continue;
            }
            $run->forceFill(['overdue_flagged_at' => Carbon::now()])->save();
            $run->audit('patrol.overdue_flagged', ['planned_minutes' => $planned]);
            $this->raiseDeviation(
                $run,
                null,
                (string) __('patrol.deviation.overdue_title', ['route' => (string) $run->route?->name]),
                (string) __('patrol.deviation.overdue_line', ['minutes' => $planned, 'start' => CarbonFmt::orgTz($run->started_at)->format('d.m.Y H:i')]) . "\n" . $this->deviationLines($run, $this->missedCheckpoints($run)->count()),
            );
            $flagged++;
        }

        return $flagged;
    }

    /** Soll-Dauer des Laufs: spätester Kontrollpunkt samt Toleranz; ohne erwartete Punkte null. */
    public function plannedMinutes(PatrolRun $run): ?int {
        $max = PatrolCheckpoint::query()
            ->withoutGlobalScopes()
            ->where('patrol_route_id', $run->patrol_route_id)
            ->expectedAt($run->started_at)
            ->selectRaw('MAX(expected_offset_minutes + tolerance_minutes) as planned')
            ->value('planned');

        return $max === null ? null : (int) $max;
    }

    /** Zuständig für Abweichungen: Verantwortliche der Route, sonst Teamleitung der Durchführenden. */
    public function responsibleFor(PatrolRun $run): ?int {
        $responsible = $run->route?->responsible_user_id;
        if ($responsible !== null) {
            return (int) $responsible;
        }
        $starter = $run->started_by !== null ? User::query()->withoutGlobalScopes()->find($run->started_by) : null;
        $lead = $starter === null ? null : $starter->teams()->whereNotNull('teams.lead_user_id')->where('teams.lead_user_id', '!=', $starter->id)->value('teams.lead_user_id');

        return $lead !== null ? (int) $lead : null;
    }

    /** Ein offener Punkt je Lauf: anlegen oder mit dem neuesten Stand fortschreiben. */
    private function raiseDeviation(PatrolRun $run, ?User $actor, string $title, ?string $description): void {
        $existing = OpenIssue::query()
            ->withoutGlobalScopes()
            ->where('subject_type', $run->getMorphClass())
            ->where('subject_id', $run->id)
            ->where('source_type', OpenIssueSource::PatrolDeviation->value)
            ->whereIn('status', [OpenIssueStatus::Open->value, OpenIssueStatus::InProgress->value, OpenIssueStatus::Reopened->value])
            ->first();
        if ($existing !== null) {
            $existing->forceFill(['title' => $title, 'description' => $description])->save();

            return;
        }

        $creator = $actor->id ?? $run->started_by ?? $run->route?->created_by;
        if ($creator === null) {
            return;
        }
        OpenIssue::query()->create([
            'organization_id' => $run->organization_id,
            'subject_type' => $run->getMorphClass(),
            'subject_id' => $run->id,
            'source_type' => OpenIssueSource::PatrolDeviation->value,
            'title' => $title,
            'description' => $description,
            'severity' => 'high',
            'status' => 'open',
            'assignee_user_id' => $this->responsibleFor($run),
            'created_by_user_id' => (int) $creator,
            'due_at' => Carbon::now()->addDay(),
        ]);
    }

    private function deviationLines(PatrolRun $run, int $missed): string {
        $lines = [];
        foreach ($run->scans()->where('in_window', false)->with('checkpoint:id,label')->get() as $scan) {
            $lines[] = (string) __('patrol.deviation.late_line', ['label' => (string) $scan->checkpoint?->label, 'delta' => ($scan->delta_minutes > 0 ? '+' : '') . $scan->delta_minutes]);
        }
        if ($missed > 0) {
            $lines[] = (string) __('patrol.deviation.missed_line', ['count' => $missed]);
        }

        return implode("\n", $lines);
    }

    /**
     * Wachbuch-Anbindung (Folgepunkt aus MVP-664): Der abgeschlossene
     * Rundgang schreibt sich selbst ins Auftragsbuch — aber nur, wenn das
     * Branchenprofil den Eintragstyp `revierfahrt` mitgebracht hat. Ohne
     * Profil kein Eintrag: Ein Wachbuch, das es fachlich nicht gibt, wird
     * nicht stillschweigend erfunden.
     */
    private function writeLogbookEntry(PatrolRun $run, User $actor, int $missed, int $late): void {
        $entryType = \App\Models\Classification\EntryType::query()
            ->where('slug', 'revierfahrt')
            ->first();
        if ($entryType === null) {
            return;
        }

        $entry = new \App\Models\Diary\DiaryEntry;
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
     * Kontrollpunkte, die dieser Lauf erwartet und die noch keinen Scan haben.
     * Ein vor dem Start deaktivierter Punkt zählt nicht.
     *
     * @return Collection<int, PatrolCheckpoint>
     */
    public function missedCheckpoints(PatrolRun $run): Collection {
        return PatrolCheckpoint::query()
            ->where('patrol_route_id', $run->patrol_route_id)
            ->expectedAt($run->started_at)
            ->whereNotIn('id', $run->scans()->pluck('patrol_checkpoint_id'))
            ->orderBy('position')
            ->get();
    }
}
