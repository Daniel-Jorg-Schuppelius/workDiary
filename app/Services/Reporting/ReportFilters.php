<?php
/*
 * Created on   : Fri Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReportFilters.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\{Customer, EntryType, Project, Team, User};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Standard-Filterset der Auswertungen (Feature 002): Zeitraum + optional
 * Kunde/Projekt/Mitarbeiter/Team/Auftragstyp/Status. Wird vom Trait
 * {@see \App\Http\Controllers\Reporting\Concerns\ResolvesStandardReportFilters}
 * aus dem Request aufgelöst (Sqid-dekodiert, org-gescopt verifiziert) und von
 * den Report-Buildern über die apply*-Helfer konsumiert — statt 36
 * Einzelimplementierungen.
 *
 * IDs sind bereits gegen die aktive Organisation verifiziert; fremde IDs
 * kommen hier nie an (Trait setzt sie still auf null, Org-Scope greift
 * zusätzlich auf Query-Ebene).
 */
final readonly class ReportFilters {
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public ?int $customerId = null,
        public ?int $projectId = null,
        public ?int $userId = null,
        public ?int $teamId = null,
        public ?int $entryTypeId = null,
        public ?string $status = null,
        public string $scope = 'mine',
    ) {}

    /**
     * Zeiteinträge filtern. time_entries kennt keinen Kunden direkt —
     * Kunde wird über die (org-gescopte) Projektliste eingeschränkt.
     *
     * @param  Builder<\App\Models\TimeEntry>  $query
     * @param  array{user?: string, project?: string}  $columns  Spalten-Overrides
     * @return Builder<\App\Models\TimeEntry>
     */
    public function applyToTimeEntryQuery(Builder $query, array $columns = []): Builder {
        $userColumn = $columns['user'] ?? 'user_id';
        $projectColumn = $columns['project'] ?? 'project_id';

        if ($this->projectId !== null) {
            $query->where($projectColumn, $this->projectId);
        } elseif ($this->customerId !== null) {
            $query->whereIn($projectColumn, Project::query()->where('customer_id', $this->customerId)->select('id'));
        }

        return $this->applyUserAndTeam($query, $userColumn);
    }

    /**
     * Aufträge (diary_entries) filtern. Status wird nur angewandt, wenn die
     * Seite ihn über das Standardset erhoben hat — die Bedeutung ist pro
     * Report ein anderes Enum, daher ist die Spalte überschreibbar.
     *
     * @param  Builder<\App\Models\DiaryEntry>  $query
     * @param  array{user?: string, customer?: string, project?: string, entryType?: string, status?: string|null}  $columns
     * @return Builder<\App\Models\DiaryEntry>
     */
    public function applyToDiaryEntryQuery(Builder $query, array $columns = []): Builder {
        if ($this->customerId !== null) {
            $query->where($columns['customer'] ?? 'customer_id', $this->customerId);
        }
        if ($this->projectId !== null) {
            $query->where($columns['project'] ?? 'project_id', $this->projectId);
        }
        if ($this->entryTypeId !== null) {
            $query->where($columns['entryType'] ?? 'entry_type_id', $this->entryTypeId);
        }
        $statusColumn = array_key_exists('status', $columns) ? $columns['status'] : 'status';
        if ($this->status !== null && $statusColumn !== null) {
            $query->where($statusColumn, $this->status);
        }

        return $this->applyUserAndTeam($query, $columns['user'] ?? 'user_id');
    }

    /**
     * Nur Mitarbeiter-/Team-Einschränkung (z. B. Abwesenheiten, Stempelzeiten
     * — Tabellen ohne Kunden-/Projektbezug).
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function applyUserAndTeam(Builder $query, string $userColumn = 'user_id'): Builder {
        if ($this->userId !== null) {
            $query->where($userColumn, $this->userId);
        }
        if ($this->teamId !== null) {
            $query->whereIn($userColumn, DB::table('team_user')->where('team_id', $this->teamId)->select('user_id'));
        }

        return $query;
    }

    /**
     * Team-Mitglieder-IDs (für Builder, die selbst aggregieren).
     *
     * @return list<int>
     */
    public function teamUserIds(): array {
        if ($this->teamId === null) {
            return [];
        }

        return array_values(array_map(
            fn($id): int => (int) $id,
            DB::table('team_user')->where('team_id', $this->teamId)->pluck('user_id')->all(),
        ));
    }

    /**
     * Sqid-kodierte Query-Parameter für Drilldown-/Export-Links, damit
     * Folgeseiten denselben Filterkontext erben. from/to sind enthalten —
     * Drilldowns bleiben so periodenstabil, auch wenn der Nutzer das globale
     * Header-Widget später umstellt.
     *
     * @return array<string, string>
     */
    public function toQueryParams(): array {
        return array_filter([
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'customer' => Sqid::encode(Customer::class, $this->customerId),
            'project' => Sqid::encode(Project::class, $this->projectId),
            'user' => Sqid::encode(User::class, $this->userId),
            'team' => Sqid::encode(Team::class, $this->teamId),
            'entry_type' => Sqid::encode(EntryType::class, $this->entryTypeId),
            'status' => (string) ($this->status ?? ''),
        ], fn(string $value): bool => $value !== '');
    }

    public function toQueryString(): string {
        return http_build_query($this->toQueryParams());
    }

    /**
     * Filterkontext für Audit/CSV-Metazeile ({@see \App\Http\Controllers\Reporting\Concerns\WritesReportCsv}).
     *
     * @return array<string, int|string>
     */
    public function toAuditArray(): array {
        return array_filter([
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'customer_id' => $this->customerId,
            'project_id' => $this->projectId,
            'user_id' => $this->userId,
            'team_id' => $this->teamId,
            'entry_type_id' => $this->entryTypeId,
            'status' => $this->status,
            'scope' => $this->scope,
        ], fn($value): bool => $value !== null && $value !== '');
    }

    /** Anzahl aktiver Feld-Filter (ohne Zeitraum/Scope). */
    public function activeCount(): int {
        return count(array_filter([
            $this->customerId, $this->projectId, $this->userId,
            $this->teamId, $this->entryTypeId, $this->status,
        ], fn($value): bool => $value !== null));
    }
}
