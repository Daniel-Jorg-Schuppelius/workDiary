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
use App\Jobs\DeliverTimeExportJob;
use App\Models\{Attendance, MonthClosure, Organization, TimeExport, TimeExportDeliveryConfig, TimeExportEvent, TimeExportLine, User};
use App\Services\Concerns\ResolvesActorId;
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
 *   2. build():    Zeilen aggregieren (Attendance je User × Lohnart,
 *                  {@see TimeExportLineAggregator}), Profil rendert Datei,
 *                  SHA-256-Hash bilden, Datei speichern, Status auf `ready`
 *                  setzen, alle betroffenen MonthClosures via
 *                  {@see MonthClosureService::lock()} von `approved` →
 *                  `locked` führen, ältere Exporte desselben Scope/Period
 *                  auf `superseded` setzen.
 *   3. markDelivered() / reject() / recordDownload(): Statuswechsel
 *      und append-only TimeExportEvent.
 */
class TimeExportService {
    use ResolvesActorId;

    public function __construct(
        private readonly MonthClosureService $closureService,
        private readonly TimeExportLineAggregator $lineAggregator,
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

        // Vollaudit 2026-07 (H1): Vollständigkeitsprüfung. MonthClosure-Zeilen
        // entstehen erst lazy beim Seitenaufruf — wer Zeitdaten im Zeitraum hat,
        // aber nie eingereicht hat, fehlte sonst still im Export (und die Datei
        // ginge per Auto-Lieferung sofort raus). Doku zeit-export.md §3/§9.
        $affected = $this->collectAffectedUserIds($org, $year, $month, $scope, $scopeUserId, $scopeTeamId);
        $missing = array_values(array_diff($affected, $closures->pluck('user_id')->map(fn($id): int => (int) $id)->all()));
        if ($missing !== []) {
            throw new TimeExportException(
                'missingClosures',
                __('Mitarbeitende mit Zeitdaten, aber ohne Monatsfreigabe im Zeitraum — Export abgebrochen.'),
                ['missing' => array_map(fn(int $id): array => ['user_id' => $id, 'status' => 'missing'], $missing)],
            );
        }

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
            $rowCount = $this->lineAggregator->aggregate($export, $userIds);

            // Preflight (A21): fehlende Pflicht-Lohnarten brechen hier mit
            // verständlicher Meldung ab, BEVOR eine fehlerhafte Datei entsteht
            // (Rollback: Export bleibt preparing, keine Datei, kein Lock).
            $this->assertWageTypeCodesResolvable($export);

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

        // Automatische Lieferung (A21): nur anstoßen, wenn für Org × Profil
        // ein aktiver Lieferkanal konfiguriert ist. Die Transaktion oben ist
        // bereits committet; der Job selbst ist idempotent je Export/Kanal.
        if (TimeExportDeliveryConfig::activeFor((int) $export->organization_id, $export->profile) !== null) {
            DeliverTimeExportJob::dispatch((int) $export->id);
        }

        return $export;
    }

    /**
     * Preflight des Lohnarten-Mappings (A21): Profile mit
     * `requires_wage_type_codes` (DATEV/Lexware) brauchen für jede Zeile
     * außer work.normal eine auflösbare externe Lohnart — Org-Mapping oder
     * wage_type_code der Zuschlagsregel. work.normal behält seinen
     * `normal_wage_type_code`-Default (Rückwärtskompatibilität); alle
     * anderen Zeilen würden mit dem Normalstunden-Default eine inhaltlich
     * falsche Datei erzeugen und brechen deshalb ab.
     */
    private function assertWageTypeCodesResolvable(TimeExport $export): void {
        /** @var array<string, array<string,mixed>> $profiles */
        $profiles = (array) config('exports.profiles', []);
        $cfg = $profiles[$export->profile] ?? [];
        if (! (bool) ($cfg['requires_wage_type_codes'] ?? false)) {
            return;
        }

        $resolver = new WageTypeResolver((int) $export->organization_id, $export->profile);

        $missing = [];
        foreach ($export->lines()->get() as $line) {
            /** @var TimeExportLine $line */
            if ($line->wage_type === 'work.normal') {
                continue;
            }
            if ($resolver->resolveCode($line) === null) {
                $missing[(string) $line->wage_type] = true;
            }
        }

        if ($missing !== []) {
            $types = array_keys($missing);
            sort($types);

            throw new TimeExportException(
                'missingWageTypeMappings',
                __('wage_types.error.missing_mappings', ['types' => implode(', ', $types)]),
                ['wage_types' => $types, 'profile' => $export->profile],
            );
        }
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

    /**
     * Statuswechsel auf `delivered` durch die automatische Lieferung
     * (A21, {@see \App\Jobs\DeliverTimeExportJob}): Akteur ist das System —
     * bewusst OHNE Auth-Fallback, damit der Nachweis nicht fälschlich einen
     * eingeloggten Benutzer als Übermittler führt.
     */
    public function markDeliveredBySystem(TimeExport $export, ?string $note = null): TimeExport {
        if ($export->status !== TimeExportStatus::Ready) {
            throw new TimeExportException(
                'wrongState',
                __('Nur Exporte im Status ready können als übermittelt markiert werden.'),
                ['status' => $export->status->value],
            );
        }

        return DB::transaction(function () use ($export, $note): TimeExport {
            $export->fill([
                'status' => TimeExportStatus::Delivered,
                'delivered_at' => CarbonImmutable::now(),
                'delivered_by_user_id' => null,
                'delivery_note' => $note,
            ])->save();

            $this->logEvent($export, 'export.delivered', null, $note);

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

    /**
     * Nutzer mit Attendance im Zeitraum innerhalb des Scopes — Grundlage der
     * Vollständigkeitsprüfung in prepare() (Vollaudit 2026-07, H1). Gleiche
     * Datenbasis wie {@see TimeExportLineAggregator::aggregate()}: wer dort
     * Zeilen bekäme, braucht eine genehmigte Monatsfreigabe.
     *
     * @return list<int>
     */
    private function collectAffectedUserIds(
        Organization $org,
        int $year,
        int $month,
        string $scope,
        ?int $scopeUserId,
        ?int $scopeTeamId,
    ): array {
        $start = CarbonImmutable::create($year, $month, 1);
        if (! $start instanceof CarbonImmutable) {
            throw new TimeExportException('invalidPeriod', __('Ungültige Periode :y-:m.', ['y' => $year, 'm' => $month]));
        }
        $start = $start->startOfMonth();

        $q = Attendance::query()
            ->where('organization_id', $org->id)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $start->endOfMonth()->toDateString())
            ->where('duration_minutes', '>', 0);

        if ($scope === 'user' && $scopeUserId !== null) {
            $q->where('user_id', $scopeUserId);
        } elseif ($scope === 'team' && $scopeTeamId !== null) {
            $q->whereHas('user', fn($u) => $u->where('team_id', $scopeTeamId));
        }

        /** @var list<int> $ids */
        $ids = $q->distinct()->pluck('user_id')->map(fn($id): int => (int) $id)->values()->all();

        return $ids;
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
    /**
     * Löscht einen NICHT übergebenen Export (Vollaudit 2026-07, N6):
     * Pflicht-Begründung, Datei + Zeilen + Lauf entfernen. Die Spur bleibt
     * als org-weites `export.deleted`-AuditLog erhalten — die
     * TimeExportEvent-Kette hängt per FK am Lauf und verschwindet mit ihm.
     */
    public function delete(TimeExport $export, string $reason, ?User $actor = null): void {
        if ($export->status === TimeExportStatus::Delivered) {
            throw new TimeExportException(
                'alreadyDelivered',
                __('Übergebene Exporte können nicht gelöscht werden — Aufbewahrungspflicht.'),
                ['export_id' => $export->id],
            );
        }

        $actorId = $this->resolveActorId($actor);
        DB::transaction(function () use ($export, $reason, $actorId): void {
            $export->audit('export.deleted', [
                'reason' => $reason,
                'actor_user_id' => $actorId,
                'period' => sprintf('%04d-%02d', (int) $export->period_year, (int) $export->period_month),
                'profile' => $export->profile,
                'file_path' => $export->file_path,
            ]);
            if ($export->file_path !== null) {
                Storage::disk('local')->delete((string) $export->file_path);
            }
            $export->lines()->delete();
            $export->delete();
        });
    }

    /** @param  array<string, mixed>|null  $payload */
    private function logEvent(TimeExport $export, string $event, ?int $actorId, ?string $note = null, ?array $payload = null): void {
        TimeExportEvent::query()->create([
            'time_export_id' => $export->id,
            'event' => $event,
            'actor_user_id' => $actorId,
            'note' => $note,
            'payload' => $payload,
        ]);
    }

}
