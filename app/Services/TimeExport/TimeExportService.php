<?php
/*
 * Created on   : Wed Jul 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeExportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\TimeExport;

use App\Enums\TimeApproval\MonthClosureStatus;
use App\Enums\TimeExport\TimeExportStatus;
use App\Models\{Attendance, MonthClosure, Organization, TimeExport, TimeExportEvent, TimeExportLine, User};
use App\Models\Scopes\OrganizationScope;
use App\Models\Surcharge\SurchargeRule;
use App\Services\Surcharge\SurchargeCalculator;
use App\Services\TimeApproval\MonthClosureService;
use App\Services\TimeExport\Profiles\ExportProfile;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Support\Facades\{Auth, DB, Storage};

/**
 * Orchestriert den ApprovedTimeExporter (MVP-019).
 *
 * Pipeline:
 *   1. prepare(): TimeExport in Status `preparing` anlegen, betroffene
 *      MonthClosures bestimmen, Vorbedingung „alle approved" prüfen.
 *   2. build():    Zeilen aggregieren (Attendance je User × Lohnart),
 *                  Profil rendert Datei, SHA-256-Hash bilden, Datei
 *                  speichern, Status auf `ready` setzen, alle betroffenen
 *                  MonthClosures via {@see MonthClosureService::lock()}
 *                  von `approved` → `locked` führen, ältere Exporte
 *                  desselben Scope/Period auf `superseded` setzen.
 *   3. markDelivered() / reject() / recordDownload(): Statuswechsel
 *      und append-only TimeExportEvent.
 *
 * Aggregation im MVP:
 *   - work.normal aus Attendance.duration_minutes (Stunden, 4 Nachkommastellen)
 *   - Erweiterungen (Nacht/Sonn/Feiertag/Urlaub/Krank/Bereitschaft/Reise)
 *     sind im ../WorkDiary-Architecture/zeit-export.md vorgesehen und greifen via gleicher Pipeline.
 */
class TimeExportService {
    public function __construct(
        private readonly MonthClosureService $closureService,
        private readonly SurchargeCalculator $surchargeCalculator,
    ) {}

    /**
     * Legt einen Export-Datensatz in Status `preparing` an und prüft, dass
     * jede betroffene Monatsfreigabe im Status `approved` liegt.
     *
     * @param  'organization'|'team'|'user'  $scope
     */
    public function prepare(
        Organization $org,
        int $year,
        int $month,
        string $profileKey,
        string $scope,
        ?int $scopeUserId = null,
        ?int $scopeTeamId = null,
        ?User $actor = null,
    ): TimeExport {
        $this->assertValidPeriod($year, $month);
        $this->assertProfileExists($profileKey);
        $this->assertScopeValid($scope);

        $closures = $this->collectClosures($org, $year, $month, $scope, $scopeUserId, $scopeTeamId);

        if ($closures->isEmpty()) {
            throw new TimeExportException(
                'noClosures',
                __('Für den gewählten Zeitraum existieren keine Monatsfreigaben.'),
                ['year' => $year, 'month' => $month, 'scope' => $scope],
            );
        }

        $notApproved = $closures->filter(fn(MonthClosure $c) => $c->status !== MonthClosureStatus::Approved
            && $c->status !== MonthClosureStatus::Locked)->values();

        if ($notApproved->isNotEmpty()) {
            throw new TimeExportException(
                'monthNotApproved',
                __('Mindestens eine Monatsfreigabe ist nicht genehmigt.'),
                [
                    'pending' => $notApproved->map(fn(MonthClosure $c) => [
                        'user_id' => (int) $c->user_id,
                        'status' => $c->status->value,
                    ])->all(),
                ],
            );
        }

        $actorId = $this->resolveActorId($actor);

        /** @var TimeExport $export */
        $export = DB::transaction(function () use ($org, $year, $month, $profileKey, $scope, $scopeUserId, $scopeTeamId, $actorId): TimeExport {
            /** @var TimeExport $export */
            $export = TimeExport::query()->create([
                'organization_id' => $org->id,
                'profile' => $profileKey,
                'period_year' => $year,
                'period_month' => $month,
                'scope' => $scope,
                'scope_user_id' => $scopeUserId,
                'scope_team_id' => $scopeTeamId,
                'status' => TimeExportStatus::Preparing,
                'rows_count' => 0,
                'created_by_user_id' => $actorId,
                'file_format' => $this->profileFormat($profileKey),
            ]);

            $this->logEvent($export, 'export.preparing', $actorId);

            return $export;
        });

        return $export;
    }

    /**
     * Aggregiert Zeilen, rendert das Profil, speichert die Datei, sperrt
     * die zugehörigen Monatsfreigaben und schreibt den Status auf `ready`.
     */
    public function build(TimeExport $export, ?User $actor = null): TimeExport {
        if ($export->status !== TimeExportStatus::Preparing) {
            throw new TimeExportException(
                'wrongState',
                __('Export befindet sich nicht im Status preparing.'),
                ['status' => $export->status->value],
            );
        }

        $actorId = $this->resolveActorId($actor);
        $org = $export->organization;
        assert($org instanceof Organization);
        $closures = $this->collectClosures(
            $org,
            $export->period_year,
            $export->period_month,
            $export->scope,
            $export->scope_user_id,
            $export->scope_team_id,
        );

        $export = DB::transaction(function () use ($export, $closures, $actorId, $actor): TimeExport {
            $userIds = $closures->pluck('user_id')->unique()->values()->all();
            $rowCount = $this->aggregateLines($export, $userIds);

            $rendered = $this->renderToStorage($export);

            // Frühere Ready/Delivered-Exporte für gleichen Scope/Period: superseded.
            $this->supersedeOlder($export, $actorId);

            $totals = $this->computeTotals($export);

            $export->fill([
                'rows_count' => $rowCount,
                'payload_hash' => $rendered['hash'],
                'file_path' => $rendered['path'],
                'file_format' => $rendered['format'],
                'totals' => $totals,
                'status' => TimeExportStatus::Ready,
            ])->save();

            $this->logEvent($export, 'export.ready', $actorId, null, [
                'hash' => $rendered['hash'],
                'rows' => $rowCount,
                'bytes' => $rendered['bytes'],
            ]);

            // MonthClosures von approved → locked führen.
            foreach ($closures as $closure) {
                if ($closure->status === MonthClosureStatus::Approved) {
                    $this->closureService->lock($closure, $actor);
                }
            }

            return $export->refresh();
        });

        // Telemetry-Light (Feature 036): aggregierter Org-Tageszähler, fire-and-forget.
        app(\App\Services\Metrics\OperationsMetricsService::class)->increment('timeExports.built', (int) $export->organization_id);

        return $export;
    }

    /**
     * Rendert das Profil des Exports und legt die Datei ab (geteilt von
     * {@see build()} und {@see updateLineCostCenter()}).
     *
     * @return array{hash: string, path: string, format: string, bytes: int}
     */
    private function renderToStorage(TimeExport $export): array {
        /** @var ExportProfile $profile */
        $profile = $this->makeProfile($export->profile);
        $export->refresh();
        $content = $profile->render($export);

        $hash = CryptoHelper::hash($content);
        $path = $this->buildPath($export, $hash);
        $disk = (string) config('exports.storage.disk', 'local');
        Storage::disk($disk)->put($path, $content);

        return [
            'hash' => $hash,
            'path' => $path,
            'format' => $profile->format(),
            'bytes' => mb_strlen($content, '8bit'),
        ];
    }

    /**
     * Kostenstellen-Override im Prüf-UI (Rang 35): Zeile korrigieren und die
     * Export-Datei neu rendern — nur solange der Export `ready` (noch nicht
     * ausgeliefert) ist. Bei einem Re-Export (superseded) gelten wieder die
     * Regeln; der Override gilt je Datei-Stand und ist auditiert.
     */
    public function updateLineCostCenter(TimeExport $export, TimeExportLine $line, ?string $costCenter, ?User $actor = null): TimeExport {
        if ($export->status !== TimeExportStatus::Ready) {
            throw new TimeExportException(
                'wrongState',
                __('Zeilen sind nur im Status ready korrigierbar.'),
                ['status' => $export->status->value],
            );
        }
        if ((int) $line->time_export_id !== (int) $export->id) {
            throw new TimeExportException('wrongExport', __('Zeile gehört nicht zu diesem Export.'));
        }

        $actorId = $this->resolveActorId($actor);

        return DB::transaction(function () use ($export, $line, $costCenter, $actorId): TimeExport {
            $previous = $line->cost_center;
            $line->update(['cost_center' => $costCenter]);

            $rendered = $this->renderToStorage($export);
            $export->fill([
                'payload_hash' => $rendered['hash'],
                'file_path' => $rendered['path'],
            ])->save();

            $this->logEvent($export, 'export.line_updated', $actorId, null, [
                'line_id' => $line->id,
                'field' => 'cost_center',
                'from' => $previous,
                'to' => $costCenter,
                'hash' => $rendered['hash'],
            ]);

            return $export->refresh();
        });
    }

    public function markDelivered(TimeExport $export, ?User $actor = null, ?string $note = null): TimeExport {
        if ($export->status !== TimeExportStatus::Ready) {
            throw new TimeExportException(
                'wrongState',
                __('Nur Exporte im Status ready können als übermittelt markiert werden.'),
                ['status' => $export->status->value],
            );
        }

        $actorId = $this->resolveActorId($actor);

        return DB::transaction(function () use ($export, $actorId, $note): TimeExport {
            $export->fill([
                'status' => TimeExportStatus::Delivered,
                'delivered_at' => CarbonImmutable::now(),
                'delivered_by_user_id' => $actorId,
                'delivery_note' => $note,
            ])->save();

            $this->logEvent($export, 'export.delivered', $actorId, $note);

            return $export->refresh();
        });
    }

    public function reject(TimeExport $export, ?User $actor = null, ?string $note = null): TimeExport {
        if (! in_array($export->status, [TimeExportStatus::Ready, TimeExportStatus::Delivered], true)) {
            throw new TimeExportException(
                'wrongState',
                __('Nur Exporte im Status ready/delivered können abgelehnt werden.'),
                ['status' => $export->status->value],
            );
        }

        $actorId = $this->resolveActorId($actor);

        return DB::transaction(function () use ($export, $actorId, $note): TimeExport {
            $export->fill(['status' => TimeExportStatus::Rejected])->save();
            $this->logEvent($export, 'export.rejected', $actorId, $note);

            return $export->refresh();
        });
    }

    public function recordDownload(TimeExport $export, ?User $actor = null): void {
        $this->logEvent($export, 'export.downloaded', $this->resolveActorId($actor));
    }

    // ── intern ─────────────────────────────────────────────────────────

    /**
     * @return \Illuminate\Support\Collection<int, MonthClosure>
     */
    private function collectClosures(
        Organization $org,
        int $year,
        int $month,
        string $scope,
        ?int $scopeUserId,
        ?int $scopeTeamId,
    ) {
        $q = MonthClosure::query()
            ->where('organization_id', $org->id)
            ->where('period_year', $year)
            ->where('period_month', $month);

        if ($scope === 'user' && $scopeUserId !== null) {
            $q->where('user_id', $scopeUserId);
        } elseif ($scope === 'team' && $scopeTeamId !== null) {
            // Team-Mitgliedschaft: hier defensiv über users.team_id, falls vorhanden.
            $q->whereHas('user', fn($u) => $u->where('team_id', $scopeTeamId));
        }

        return $q->get();
    }

    /** @param  array<int,int>  $userIds */
    private function aggregateLines(TimeExport $export, array $userIds): int {
        $start = CarbonImmutable::create($export->period_year, $export->period_month, 1);
        if (! $start instanceof CarbonImmutable) {
            throw new TimeExportException('invalidPeriod', __('Ungültige Periode :y-:m.', ['y' => $export->period_year, 'm' => $export->period_month]));
        }
        $start = $start->startOfMonth();
        $end = $start->endOfMonth();

        // Bestehende Zeilen verwerfen (idempotente Re-Aggregation).
        $export->lines()->delete();

        // Zuschlagsregeln der Organisation einmalig laden (Feature 005).
        $surchargeRules = SurchargeRule::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $export->organization_id)
            ->where('active', true)
            ->orderBy('id')
            ->get();

        // Kostenstellen-Regeln (Rang 35): Benutzer > Team > Org-Default.
        $costCenters = new CostCenterResolver((int) $export->organization_id);

        $rows = 0;
        foreach ($userIds as $uid) {
            $minutes = (int) Attendance::query()
                ->where('user_id', $uid)
                ->whereDate('date', '>=', $start->toDateString())
                ->whereDate('date', '<=', $end->toDateString())
                ->sum('duration_minutes');

            if ($minutes <= 0) {
                continue;
            }

            $hours = round($minutes / 60, 4);
            $costCenter = $costCenters->forUser((int) $uid);

            TimeExportLine::query()->create([
                'time_export_id' => $export->id,
                'user_id' => $uid,
                'wage_type' => 'work.normal',
                'cost_center' => $costCenter,
                'quantity' => $hours,
                'unit' => 'h',
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'note' => null,
                'source_refs' => ['attendance_minutes' => $minutes],
            ]);

            $rows++;

            $rows += $this->aggregateSurchargeLines($export, $uid, $start, $end, $surchargeRules, $costCenter);
        }

        return $rows;
    }

    /**
     * Zuschlagszeilen je User × Kalendertag × Regel (Feature 005, additiv).
     *
     * Grundlage sind die Attendance-Intervalle (started_at→ended_at) des
     * Monats; der {@see SurchargeCalculator} zerlegt sie in zuschlagsfähige
     * Segmente (Stacking: höchster Prozentsatz gewinnt, kein Addieren).
     * Hinweis (MVP): Pausen sind zeitlich nicht verortet und werden daher
     * im Zuschlagsfenster nicht abgezogen — gerechnet wird auf dem
     * Brutto-Intervall der Anwesenheit.
     *
     * @param  \Illuminate\Support\Collection<int, SurchargeRule>  $rules
     * @return int Anzahl erzeugter Zeilen
     */
    private function aggregateSurchargeLines(
        TimeExport $export,
        int $uid,
        CarbonImmutable $start,
        CarbonImmutable $end,
        \Illuminate\Support\Collection $rules,
        ?string $costCenter = null,
    ): int {
        if ($rules->isEmpty()) {
            return 0;
        }

        $attendances = Attendance::query()
            ->where('user_id', $uid)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->whereNotNull('started_at')
            ->whereNotNull('ended_at')
            ->orderBy('started_at')
            ->get(['id', 'started_at', 'ended_at']);

        // Akkumulieren: je (Regel, Kalendertag) Minuten + Quell-Attendances.
        /** @var array<string, array{rule: SurchargeRule, date: string, minutes: int, sources: list<int>}> $acc */
        $acc = [];
        foreach ($attendances as $attendance) {
            $shares = $this->surchargeCalculator->calculate(
                CarbonImmutable::parse((string) $attendance->started_at),
                CarbonImmutable::parse((string) $attendance->ended_at),
                $rules,
            );

            foreach ($shares as $share) {
                $key = $share->date . '|' . $share->rule->id;
                if (! isset($acc[$key])) {
                    $acc[$key] = [
                        'rule' => $share->rule,
                        'date' => $share->date,
                        'minutes' => 0,
                        'sources' => [],
                    ];
                }
                $acc[$key]['minutes'] += $share->minutes;
                $acc[$key]['sources'][] = (int) $attendance->id;
            }
        }

        ksort($acc);

        $rows = 0;
        foreach ($acc as $row) {
            if ($row['minutes'] <= 0) {
                continue;
            }

            $base = [
                'time_export_id' => $export->id,
                'user_id' => $uid,
                'wage_type' => $row['rule']->wageType(),
                'cost_center' => $costCenter,
                'quantity' => round($row['minutes'] / 60, 4),
                'unit' => 'h',
                'period_start' => $row['date'],
                'period_end' => $row['date'],
                'source_refs' => ['attendance_ids' => array_values(array_unique($row['sources']))],
                'surcharge_rule_id' => $row['rule']->id,
            ];

            // Steuerfrei/-pflichtig-Split (Rang 36): über der steuerfreien
            // Obergrenze wird der Prozentsatz in zwei Zeilen mit getrennten
            // Lohnarten geteilt — gleiche Stunden, die externe Lohnrechnung
            // rechnet je Anteil (€-Deckel bleibt dort).
            $split = $row['rule']->taxSplit();
            if ($split === null) {
                TimeExportLine::query()->create($base + [
                    'note' => $row['rule']->label,
                    'wage_type_code' => $row['rule']->wage_type_code,
                    'percentage' => $row['rule']->percentage,
                ]);

                $rows++;

                continue;
            }

            TimeExportLine::query()->create($base + [
                'note' => $row['rule']->label . ' — ' . __('steuerfrei'),
                'wage_type_code' => $row['rule']->wage_type_code,
                'percentage' => $split['free_pct'],
            ]);
            TimeExportLine::query()->create($base + [
                'note' => $row['rule']->label . ' — ' . __('steuerpflichtig'),
                'wage_type_code' => $row['rule']->taxable_wage_type_code ?? $row['rule']->wage_type_code,
                'percentage' => $split['taxable_pct'],
            ]);

            $rows += 2;
        }

        return $rows;
    }

    /** @return array<string, array{quantity: float, unit: string}> */
    private function computeTotals(TimeExport $export): array {
        $totals = [];
        foreach ($export->lines()->get() as $line) {
            $key = $line->wage_type;
            if (! isset($totals[$key])) {
                $totals[$key] = ['quantity' => 0.0, 'unit' => $line->unit];
            }
            $totals[$key]['quantity'] += (float) $line->quantity;
        }
        foreach ($totals as $k => $t) {
            $totals[$k]['quantity'] = round($t['quantity'], 4);
        }

        return $totals;
    }

    private function supersedeOlder(TimeExport $current, ?int $actorId): void {
        $q = TimeExport::query()
            ->where('organization_id', $current->organization_id)
            ->where('period_year', $current->period_year)
            ->where('period_month', $current->period_month)
            ->where('scope', $current->scope)
            ->where('profile', $current->profile)
            ->where('id', '<>', $current->id)
            ->whereIn('status', [TimeExportStatus::Ready->value, TimeExportStatus::Delivered->value]);

        if ($current->scope === 'user') {
            $q->where('scope_user_id', $current->scope_user_id);
        } elseif ($current->scope === 'team') {
            $q->where('scope_team_id', $current->scope_team_id);
        }

        foreach ($q->get() as $old) {
            /** @var TimeExport $old */
            $old->fill([
                'status' => TimeExportStatus::Superseded,
                'superseded_by_id' => $current->id,
            ])->save();
            $this->logEvent($old, 'export.superseded', $actorId, null, ['by' => $current->id]);
        }
    }

    private function buildPath(TimeExport $export, string $hash): string {
        $pattern = (string) config('exports.storage.path_pattern', 'exports/{org}/{year}-{month}/{profile}-{hash}.{ext}');

        return strtr($pattern, [
            '{org}' => (string) $export->organization_id,
            '{year}' => sprintf('%04d', $export->period_year),
            '{month}' => sprintf('%02d', $export->period_month),
            '{profile}' => $export->profile,
            '{hash}' => substr($hash, 0, 16),
            '{ext}' => $this->profileFormat($export->profile),
        ]);
    }

    private function makeProfile(string $key): ExportProfile {
        /** @var array<string, array<string,mixed>> $profiles */
        $profiles = (array) config('exports.profiles', []);
        $cfg = $profiles[$key] ?? null;
        if (! is_array($cfg) || ! isset($cfg['driver']) || ! is_string($cfg['driver'])) {
            throw new TimeExportException('profileUnknown', __('Unbekanntes Export-Profil :p.', ['p' => $key]));
        }

        /** @var class-string<ExportProfile> $driver */
        $driver = $cfg['driver'];
        /** @var array<string,mixed> $options */
        $options = isset($cfg['options']) && is_array($cfg['options']) ? $cfg['options'] : [];

        /** @var ExportProfile $instance */
        $instance = app()->make($driver, ['options' => $options]);

        return $instance;
    }

    private function profileFormat(string $key): string {
        /** @var array<string, array<string,mixed>> $profiles */
        $profiles = (array) config('exports.profiles', []);
        $cfg = $profiles[$key] ?? null;
        if (is_array($cfg) && isset($cfg['format']) && is_string($cfg['format'])) {
            return $cfg['format'];
        }

        return 'csv';
    }

    private function assertProfileExists(string $key): void {
        /** @var array<string, array<string,mixed>> $profiles */
        $profiles = (array) config('exports.profiles', []);
        $cfg = $profiles[$key] ?? null;
        if (! is_array($cfg) || ! isset($cfg['driver']) || ! is_string($cfg['driver'])) {
            throw new TimeExportException('profileUnknown', __('Unbekanntes Export-Profil :p.', ['p' => $key]));
        }
    }

    private function assertScopeValid(string $scope): void {
        if (! in_array($scope, ['organization', 'team', 'user'], true)) {
            throw new TimeExportException('invalidScope', __('Ungültiger Export-Scope :s.', ['s' => $scope]));
        }
    }

    private function assertValidPeriod(int $year, int $month): void {
        if ($year < 2000 || $year > 2999 || $month < 1 || $month > 12) {
            throw new TimeExportException('invalidPeriod', __('Ungültige Periode :y-:m.', ['y' => $year, 'm' => $month]));
        }
    }

    /** @param  array<string,mixed>|null  $payload */
    private function logEvent(TimeExport $export, string $event, ?int $actorId, ?string $note = null, ?array $payload = null): void {
        TimeExportEvent::query()->create([
            'time_export_id' => $export->id,
            'event' => $event,
            'actor_user_id' => $actorId,
            'note' => $note,
            'payload' => $payload,
        ]);
    }

    private function resolveActorId(?User $actor): ?int {
        if ($actor instanceof User) {
            return (int) $actor->id;
        }
        $id = Auth::id();

        return $id === null ? null : (int) $id;
    }
}
