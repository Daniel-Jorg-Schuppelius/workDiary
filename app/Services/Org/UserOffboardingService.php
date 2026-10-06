<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserOffboardingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Org;

use App\Models\Platform\User;
use App\Modules\ModuleRegistry;
use App\Services\Org\Contracts\OffboardingStep;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mitarbeiter-Austritt (Feature 126, MVP-689 — Vollscan H1/E4): Der Regelweg
 * für ausscheidende Mitarbeiter ist DEAKTIVIEREN, nicht löschen — die
 * Arbeitszeit-/Lohn-Nachweise (ArbZG § 16 Abs. 2, MiLoG § 17, GoBD) bleiben
 * personengebunden stehen und sind seit Migration 101000 per RESTRICT-FK
 * geschützt. Hard-Delete bleibt nur für Konten OHNE Nachweise erlaubt
 * (Fehlanlage). Ein deaktiviertes Konto gibt seinen Lizenzsitz frei
 * ({@see \App\Models\Platform\Organization::activeUserCount()}).
 */
class UserOffboardingService {
    /**
     * Alle Arbeitszeit-/Lohn-Nachweistabellen mit RESTRICT-FK auf users
     * (Migration 2027_02_19_101000) — z. B. für Aufräumpfade wie den
     * Demo-Reset, die VOR einem User-Delete leerräumen müssen.
     *
     * @var array<string, string> Tabelle => user-Spalte
     */
    public const RETENTION_FK_TABLES = [
        'attendances' => 'user_id',
        'time_entries' => 'user_id',
        'timesheets' => 'user_id',
        'time_export_lines' => 'user_id',
        'month_closures' => 'user_id',
        'day_closures' => 'user_id',
        'time_account_entries' => 'user_id',
        'time_account_balances' => 'user_id',
        'flex_balances' => 'user_id',
        'overtime_requests' => 'user_id',
        'time_correction_requests' => 'user_id',
        'vacations' => 'user_id',
        'vacation_entitlements' => 'user_id',
        'sick_leaves' => 'user_id',
        'expenses' => 'user_id',
        'per_diem_trips' => 'user_id',
        'travel_logs' => 'user_id',
        'external_wage_items' => 'user_id',
        'work_schedules' => 'user_id',
    ];

    /** Kern-Nachweise, die einen Hard-Delete ausschließen (Tabelle => Spalte). */
    private const EVIDENCE_TABLES = [
        'time_entries' => 'user_id',
        'attendances' => 'user_id',
        'timesheets' => 'user_id',
        'time_export_lines' => 'user_id',
        'expenses' => 'user_id',
        'vacations' => 'user_id',
        'sick_leaves' => 'user_id',
        // Nachweise mit Personenbezug in der Urheberschaft (Sicherheitsscan
        // 2026-08-23, S-08): sie hingen bis dahin per CASCADE am Konto und
        // verschwanden beim Entfernen des Mitglieds — Protokolle samt
        // Kundenunterschrift, Vernichtungsnachweise, Formularrückläufe.
        'protocols' => 'created_by_user_id',
        'documents' => 'created_by_user_id',
        'disposal_jobs' => 'created_by_user_id',
        'disposal_handovers' => 'created_by_user_id',
        'diary_entries' => 'user_id',
        'form_submissions' => 'submitted_by_user_id',
        'safety_events' => 'reported_by_user_id',
        'tours' => 'user_id',
        // Entscheidung 2026-10-05 (Konsolidierungs-Audit k3-13): vier neuere
        // Tabellen mit CASCADE auf das Konto sind ebenfalls Nachweise.
        'personnel_file_submissions' => 'user_id',
        'personnel_file_acknowledgements' => 'user_id',
        'comments' => 'user_id',
        'crisis_room_presences' => 'user_id',
    ];

    /**
     * Austritt einleiten: Datum setzen; liegt es nicht in der Zukunft, wird
     * sofort vollzogen. Zukünftige Austritte vollzieht `runDue()` am Stichtag.
     */
    /**
     * Übergabeliste für den Austritts-Dialog (MVP-798, Befund P1-21): was die
     * Person noch hält oder offen hat — aus dem Bestand berechnet, kein eigenes
     * Modell. Sperrgründe (z. B. Zutrittsmedien) liefern die Offboarding-Schritte.
     *
     * @return array{blockers: list<string>, assets: \Illuminate\Database\Eloquent\Collection<int, \App\Models\Asset\AssetAssignment>, tasks: \Illuminate\Database\Eloquent\Collection<int, \App\Models\Project\Task>, open_attendances: int, deputies: \Illuminate\Database\Eloquent\Collection<int, User>}
     */
    public function handoverChecklist(User $member): array {
        return [
            'blockers' => $this->blockers($member),
            'assets' => \App\Models\Asset\AssetAssignment::query()->open()
                ->where('assigned_to_user_id', $member->id)
                ->with('asset')
                ->get(),
            'tasks' => \App\Models\Project\Task::query()
                ->whereHas('assignees', fn ($query) => $query->whereKey($member->id))
                ->where('status', '!=', \App\Enums\Task\TaskStatus::Done->value)
                ->orderBy('title')
                ->get(),
            'open_attendances' => \App\Models\Time\Attendance::query()->open()->where('user_id', $member->id)->count(),
            // MVP-941: Personen, die das austretende Mitglied als Vertretung eingetragen haben.
            'deputies' => User::query()->where('organization_id', $member->organization_id)->where('deputy_user_id', $member->id)->whereKeyNot($member->id)->orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * Vertretungen neu besetzen (MVP-941): Person → neue Vertretung oder null.
     *
     * @param array<int, int|null> $replacements
     */
    public function replaceDeputies(User $member, array $replacements, User $actor): void {
        foreach (User::query()->where('organization_id', $member->organization_id)->where('deputy_user_id', $member->id)->get() as $user) {
            $new = $replacements[$user->id] ?? null;
            $new = $new !== null && $new !== $member->id && $new !== $user->id ? $new : null;
            $user->forceFill(['deputy_user_id' => $new])->save();
            $user->audit('user.deputyReplaced', ['previous_user_id' => $member->id, 'deputy_user_id' => $new, 'by_user_id' => $actor->id]);
        }
    }

    public function initiate(User $member, CarbonImmutable $leftAt, User $actor): void {
        $member->forceFill(['left_at' => $leftAt->toDateString()])->save();
        $member->audit('user.offboardingScheduled', [
            'left_at' => $leftAt->toDateString(),
            'by_user_id' => $actor->id,
        ]);

        if (! $leftAt->isFuture()) {
            $this->execute($member, $actor);
        }
    }

    /**
     * Austritt vollziehen: Konto sperren (canLogin), Sessions beenden,
     * API-Tokens widerrufen, Remember-Token rotieren. Idempotent.
     */
    public function execute(User $member, ?User $actor = null): void {
        if ($member->isDeactivated()) {
            return;
        }

        $member->forceFill([
            'deactivated_at' => now(),
            'left_at' => $member->left_at ?? now()->toDateString(),
            'remember_token' => Str::random(60),
            // Kalender-Abo und Gerätetoken sind Zugänge ohne Sitzung: sie liefen
            // nach dem Austritt weiter (Sicherheitsaudit 2026-09-17, offboard-1).
            'calendar_feed_token_hash' => null,
        ])->save();

        \App\Models\Location\LocationDeviceToken::query()
            ->where('user_id', $member->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        // Aktive Sitzungen und API-Zugänge enden mit dem Austritt sofort.
        DB::table('sessions')->where('user_id', $member->id)->delete();
        DB::table('personal_access_tokens')
            ->where('tokenable_type', $member->getMorphClass())
            ->where('tokenable_id', $member->id)
            ->delete();

        // Verbliebene Vertretungen auf die ausgetretene Person enden (MVP-941).
        User::query()->withoutGlobalScopes()->where('organization_id', $member->organization_id)->where('deputy_user_id', $member->id)->update(['deputy_user_id' => null]);

        foreach ($this->steps() as $step) {
            $step->onExit($member);
        }

        $member->audit('user.offboarded', [
            'left_at' => (string) $member->left_at?->toDateString(),
            'by_user_id' => $actor?->id,
        ]);
    }

    /** Fällige, noch aktive Austritte vollziehen (Scheduler). */
    public function runDue(): int {
        $due = User::withoutGlobalScopes()
            ->whereNull('deactivated_at')
            ->whereNotNull('left_at')
            ->whereDate('left_at', '<=', now()->toDateString())
            ->get();

        foreach ($due as $member) {
            $this->execute($member);
        }

        return $due->count();
    }

    /** Hat das Konto aufbewahrungspflichtige Nachweise? Dann ist Löschen tabu. */
    public function hasEvidence(User $member): bool {
        foreach (self::EVIDENCE_TABLES as $table => $column) {
            if (DB::table($table)->where($column, $member->id)->exists()) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> Sperrgründe aller Offboarding-Schritte (Austritt und Entfernen) */
    public function blockers(User $member): array {
        return array_merge([], ...array_map(static fn (OffboardingStep $step): array => $step->blockers($member), $this->steps()));
    }

    /** @return list<OffboardingStep> */
    private function steps(): array {
        return array_map(static fn (string $class): OffboardingStep => app($class), app(ModuleRegistry::class)->extensions(OffboardingStep::class));
    }
}
