<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OperationsMetricsService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Metrics;

use App\Models\{Attachment, AuditLog, BackupHeartbeat, CommunicationNote, DiaryEntry, Document, DocumentVersion, FeatureUsageCounter, FormSubmission, KnowledgeArticle, Organization, PluginError, Protocol, User};
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\{Auth, DB};
use Throwable;

/**
 * Betriebsmetriken (Feature 036, MVP) — READ-ONLY-Sammlung für die
 * Admin-Seite „Betriebsmetriken" plus der einzige Schreibpfad
 * {@see increment()} für die datenschutzfreundlichen Nutzungszähler.
 *
 * Abgrenzung zur Diagnose-Seite (MVP-044, DiagnosticsService): dort liegen
 * die Health-CHECKS mit Status-Ampeln (Mail-Config, Scheduler-/Worker-
 * Heartbeats, Dateisystem-Füllgrad, Lizenz, Backup-Schwellwerte). Hier
 * stehen reine KENNZAHLEN: Queue-Stand, letzte Backup-Heartbeats,
 * Plugin-Fehler der letzten 7 Tage, Speicher als DB-Metadaten-Summen
 * (bewusst KEIN Filesystem-Walk — den macht die Diagnose bereits),
 * Datensatzzählungen je Kernmodul, aktive Benutzer und Feature-Nutzung.
 *
 * Mandanten-Sicht: alle org-gebundenen Modelle (DiaryEntry, Document, …,
 * FeatureUsageCounter, AuditLog, Attachment) tragen BelongsToOrganization;
 * ist `currentOrganization` gebunden (Org-Admin), sind die Zahlen damit
 * automatisch org-bezogen, beim globalen Admin ohne Org org-übergreifend.
 *
 * Datenschutz: keine Inhalte, keine Einzel-User-Auswertung, kein externes
 * Senden — alles bleibt in der lokalen Datenbank.
 */
class OperationsMetricsService {
    /** Zeitraum (Tage) für die aggregierte Feature-Nutzung. */
    public const USAGE_WINDOW_DAYS = 30;

    /** Zeitraum (Tage) für die Plugin-Fehler-Auswertung. */
    public const PLUGIN_ERROR_WINDOW_DAYS = 7;

    /**
     * Sammelt alle Kennzahlen. Jede Sektion ist gegen Fehler isoliert —
     * eine kaputte Quelle (fehlende Tabelle etc.) reißt die Seite nicht um.
     *
     * @return array<string, mixed>
     */
    public function collect(): array {
        return [
            'version' => (string) config('app.version', '0.1.0-dev'),
            'queue' => $this->safe(fn(): array => $this->queueMetrics(), ['available' => false]),
            'backups' => $this->safe(fn(): array => $this->backupMetrics(), []),
            'plugin_errors' => $this->safe(fn(): array => $this->pluginErrorMetrics(), ['count' => 0, 'recent' => []]),
            'storage' => $this->safe(fn(): array => $this->storageMetrics(), []),
            'active_users' => $this->safe(fn(): ?int => $this->activeUserCount(), null),
            'module_counts' => $this->safe(fn(): array => $this->moduleCounts(), []),
            'feature_usage' => $this->safe(fn(): array => $this->featureUsage(), []),
            'generated_at' => CarbonImmutable::now(),
        ];
    }

    /**
     * Zählt eine Feature-Nutzung hoch (Telemetry-Light): ein Aggregat pro
     * Organisation + Feature + Tag, fire-and-forget — Fehler hier dürfen
     * NIE den fachlichen Ablauf brechen, daher schluckt die Methode alles.
     */
    public function increment(string $feature, ?int $organizationId = null): void {
        try {
            $organizationId ??= $this->resolveOrganizationId();
            if ($organizationId === null || $feature === '') {
                return;
            }

            $today = CarbonImmutable::now()->toDateString();
            $updated = DB::table('feature_usage_counters')
                ->where('organization_id', $organizationId)
                ->where('feature', $feature)
                ->where('period_date', $today)
                ->increment('count');

            if ($updated === 0) {
                try {
                    DB::table('feature_usage_counters')->insert([
                        'organization_id' => $organizationId,
                        'feature' => $feature,
                        'period_date' => $today,
                        'count' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (QueryException) {
                    // Race auf den Unique-Index (fuc_org_feature_period_uq):
                    // eine parallele Anfrage hat die Zeile angelegt — nachziehen.
                    DB::table('feature_usage_counters')
                        ->where('organization_id', $organizationId)
                        ->where('feature', $feature)
                        ->where('period_date', $today)
                        ->increment('count');
                }
            }
        } catch (Throwable) {
            // bewusst still — Telemetrie ist nie geschäftskritisch.
        }
    }

    /**
     * Queue-Stand aus den jobs-/failed_jobs-Tabellen (Database-Driver).
     * Fehlen die Tabellen (z. B. sync-Driver), wird die Sektion als
     * nicht verfügbar markiert.
     *
     * @return array<string, mixed>
     */
    private function queueMetrics(): array {
        $schema = DB::getSchemaBuilder();
        $hasJobs = $schema->hasTable('jobs');
        $hasFailed = $schema->hasTable('failed_jobs');

        if (! $hasJobs && ! $hasFailed) {
            return ['available' => false];
        }

        return [
            'available' => true,
            'pending' => $hasJobs ? (int) DB::table('jobs')->count() : null,
            'failed' => $hasFailed ? (int) DB::table('failed_jobs')->count() : null,
        ];
    }

    /**
     * Die letzten Backup-Heartbeats (MVP-046 §5) — systemweit, da der
     * externe Backup-Job ohne Tenant-Kontext postet.
     *
     * @return list<array<string, mixed>>
     */
    private function backupMetrics(): array {
        if (! DB::getSchemaBuilder()->hasTable((new BackupHeartbeat())->getTable())) {
            return [];
        }

        return array_values(BackupHeartbeat::query()
            ->orderByDesc('occurred_at')
            ->limit(5)
            ->get()
            ->map(static fn(BackupHeartbeat $hb): array => [
                'occurred_at' => $hb->occurred_at,
                'size_bytes' => $hb->size_bytes,
                'source' => $hb->source,
            ])
            ->all());
    }

    /**
     * Plugin-Fehler der letzten 7 Tage. Org-Admins sehen die eigenen
     * plus globale (organization_id NULL) Einträge.
     *
     * @return array{count: int, recent: list<array<string, mixed>>}
     */
    private function pluginErrorMetrics(): array {
        $since = CarbonImmutable::now()->subDays(self::PLUGIN_ERROR_WINDOW_DAYS);
        $org = $this->currentOrganization();

        $query = PluginError::query()->where('occurred_at', '>=', $since);
        if ($org !== null) {
            $query->where(static function ($q) use ($org): void {
                $q->where('organization_id', $org->id)->orWhereNull('organization_id');
            });
        }

        return [
            'count' => (int) (clone $query)->count(),
            'recent' => array_values($query->orderByDesc('occurred_at')
                ->limit(5)
                ->get(['plugin_id', 'phase', 'message', 'occurred_at'])
                ->map(static fn(PluginError $e): array => [
                    'plugin_id' => $e->plugin_id,
                    'phase' => $e->phase,
                    'message' => $e->message,
                    'occurred_at' => $e->occurred_at,
                ])
                ->all()),
        ];
    }

    /**
     * Speicher als Summe der DB-Metadaten (Anzahl + Bytes) von Anhängen
     * und Dokument-Versionen. Bewusst KEIN du-artiger Filesystem-Walk:
     * den (teuren) Disk-Scan macht bereits die Diagnose-Seite.
     *
     * @return array<string, array{count: int, bytes: int}>
     */
    private function storageMetrics(): array {
        // Attachment ist org-scoped (BelongsToOrganization). DocumentVersion
        // ist ein Child von Document — whereHas('document') zieht den
        // Org-Scope des Parents transitiv mit.
        return [
            'attachments' => [
                'count' => (int) Attachment::query()->count(),
                'bytes' => (int) Attachment::query()->sum('size'),
            ],
            'document_versions' => [
                'count' => (int) DocumentVersion::query()->whereHas('document')->count(),
                'bytes' => (int) DocumentVersion::query()->whereHas('document')->sum('size'),
            ],
        ];
    }

    /**
     * Aktive Benutzer: distinct Logins (audit_logs, Event `auth.login`)
     * der letzten 30 Tage. Eine last_login-Spalte existiert nicht — das
     * Audit-Log ist die verlässlichste vorhandene Quelle.
     */
    private function activeUserCount(): ?int {
        if (! DB::getSchemaBuilder()->hasTable((new AuditLog())->getTable())) {
            return null;
        }

        return (int) AuditLog::query()
            ->where('event', 'auth.login')
            ->where('created_at', '>=', CarbonImmutable::now()->subDays(30))
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count('user_id');
    }

    /**
     * Datensatz-Zählungen je Kernmodul (org-scoped über die Global Scopes).
     *
     * @return array<string, int>
     */
    private function moduleCounts(): array {
        $models = [
            'diary_entries' => DiaryEntry::class,
            'protocols' => Protocol::class,
            'documents' => Document::class,
            'form_submissions' => FormSubmission::class,
            'knowledge_articles' => KnowledgeArticle::class,
            'communication_notes' => CommunicationNote::class,
        ];

        $counts = [];
        foreach ($models as $key => $model) {
            $counts[$key] = $this->safe(static fn(): int => (int) $model::query()->count(), 0);
        }

        return $counts;
    }

    /**
     * Aggregierte Feature-Nutzung der letzten 30 Tage, je Feature summiert.
     *
     * @return list<array{feature: string, total: int, last_used_on: string|null}>
     */
    private function featureUsage(): array {
        $since = CarbonImmutable::now()->subDays(self::USAGE_WINDOW_DAYS)->toDateString();

        return array_values(FeatureUsageCounter::query()
            ->where('period_date', '>=', $since)
            ->groupBy('feature')
            ->orderBy('feature')
            ->selectRaw('feature, SUM(count) as total, MAX(period_date) as last_used_on')
            ->get()
            ->map(static fn(FeatureUsageCounter $row): array => [
                'feature' => (string) $row->feature,
                'total' => (int) $row->getAttribute('total'),
                'last_used_on' => $row->getAttribute('last_used_on') !== null ? (string) $row->getAttribute('last_used_on') : null,
            ])
            ->all());
    }

    private function resolveOrganizationId(): ?int {
        $org = $this->currentOrganization();
        if ($org !== null) {
            return (int) $org->id;
        }

        $user = Auth::user();
        if ($user instanceof User && ! empty($user->organization_id)) {
            return (int) $user->organization_id;
        }

        return null;
    }

    private function currentOrganization(): ?Organization {
        if (! app()->bound('currentOrganization')) {
            return null;
        }

        $org = app('currentOrganization');

        return $org instanceof Organization ? $org : null;
    }

    /**
     * @template TValue
     * @param  \Closure(): TValue  $callback
     * @param  TValue  $fallback
     * @return TValue
     */
    private function safe(\Closure $callback, mixed $fallback): mixed {
        try {
            return $callback();
        } catch (Throwable) {
            return $fallback;
        }
    }
}
