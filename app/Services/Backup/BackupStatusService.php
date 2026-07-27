<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupStatusService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Backup;

use App\Enums\Backup\RestoreTestResult;
use App\Models\{BackupHeartbeat, RestoreTest};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Liest den plattformweiten Backup-/Restore-Status für die Admin-Statusseite
 * (Feature 017) zusammen. Read-only — keine Schreibpfade.
 *
 * Plattformweit (kein Tenant-Kontext): BackupHeartbeat und RestoreTest sind
 * systemweite Modelle (siehe TenantTraitCoverage-Allow-List). Die
 * Frische-/Überfälligkeits-Schwellen kommen aus config('backup.*').
 *
 * Abgrenzung zur Diagnose-Seite (MVP-044) und zu den Betriebsmetriken
 * (Feature 036): dort werden Heartbeats nur AM RANDE gezeigt; diese Seite ist
 * die fachliche Heimat für „letzte Sicherung je Quelle + Frische-Warnung +
 * Restore-Test-Register".
 */
class BackupStatusService {
    /**
     * @return array{
     *     sources: list<array<string, mixed>>,
     *     freshness_hours: int,
     *     has_any_heartbeat: bool,
     *     restore: array{overdue: bool, overdue_days: int, last_passed_on: CarbonImmutable|null, days_since: int|null},
     *     generated_at: CarbonImmutable,
     * }
     */
    public function collect(): array {
        $freshnessHours = max(1, (int) config('backup.heartbeat_freshness_hours', 26));
        $overdueDays = max(1, (int) config('backup.restore_test_overdue_days', 180));

        return [
            'sources' => $this->sources($freshnessHours),
            'freshness_hours' => $freshnessHours,
            'has_any_heartbeat' => $this->hasAnyHeartbeat(),
            'restore' => $this->restoreStatus($overdueDays),
            'generated_at' => CarbonImmutable::now(),
        ];
    }

    /**
     * Jüngster Heartbeat je Quelle (group by source), inkl. Alter und
     * Überfälligkeits-Flag. Ein NULL-source wird als „—" geführt.
     *
     * @return list<array<string, mixed>>
     */
    private function sources(int $freshnessHours): array {
        if (! $this->heartbeatTableExists()) {
            return [];
        }

        $now = CarbonImmutable::now();
        $threshold = $now->subHours($freshnessHours);

        // Pro Quelle den jüngsten Heartbeat. Bewusst in PHP gruppiert, damit
        // wir je Quelle Größe und Manifest-Hash des aktuellsten Laufs mitführen
        // (ein reines MAX(occurred_at) je Gruppe verliert diese Spalten).
        $latestPerSource = [];
        BackupHeartbeat::query()
            ->orderByDesc('occurred_at')
            ->get()
            ->each(static function (BackupHeartbeat $hb) use (&$latestPerSource): void {
                $key = $hb->source ?? '';
                if (! \array_key_exists($key, $latestPerSource)) {
                    $latestPerSource[$key] = $hb;
                }
            });

        $rows = [];
        foreach ($latestPerSource as $key => $hb) {
            // occurred_at ist NOT NULL (Migration) — kein defensiver Null-Check nötig.
            $occurredAt = $hb->occurred_at;
            $rows[] = [
                'source' => $key === '' ? null : $key,
                'occurred_at' => $occurredAt,
                'age_hours' => (int) $occurredAt->diffInHours($now),
                'size_bytes' => $hb->size_bytes?->getBytes(),
                'manifest_hash' => $hb->manifest_hash,
                'overdue' => $occurredAt->lessThan($threshold),
            ];
        }

        // Stabile Sortierung: überfällige zuerst, dann alphabetisch nach Quelle.
        usort($rows, static function (array $a, array $b): int {
            return ((int) $b['overdue'] <=> (int) $a['overdue'])
                ?: ((string) ($a['source'] ?? '') <=> (string) ($b['source'] ?? ''));
        });

        return $rows;
    }

    private function hasAnyHeartbeat(): bool {
        return $this->heartbeatTableExists() && BackupHeartbeat::query()->exists();
    }

    /**
     * Status des Restore-Test-Registers: jüngster ERFOLGREICHER Test
     * (result = passed) und ob er die Überfälligkeitsschwelle überschritten
     * hat (oder ganz fehlt).
     *
     * @return array{overdue: bool, overdue_days: int, last_passed_on: CarbonImmutable|null, days_since: int|null}
     */
    private function restoreStatus(int $overdueDays): array {
        $lastPassedOn = null;
        $daysSince = null;

        if ($this->restoreTableExists()) {
            /** @var RestoreTest|null $lastPassed */
            $lastPassed = RestoreTest::query()
                ->where('result', RestoreTestResult::Passed->value)
                ->orderByDesc('tested_on')
                ->first();

            if ($lastPassed !== null) {
                $lastPassedOn = $lastPassed->tested_on;
                $daysSince = (int) $lastPassedOn->startOfDay()->diffInDays(CarbonImmutable::now()->startOfDay());
            }
        }

        $overdue = $daysSince === null || $daysSince > $overdueDays;

        return [
            'overdue' => $overdue,
            'overdue_days' => $overdueDays,
            'last_passed_on' => $lastPassedOn,
            'days_since' => $daysSince,
        ];
    }

    private function heartbeatTableExists(): bool {
        try {
            return DB::getSchemaBuilder()->hasTable((new BackupHeartbeat())->getTable());
        } catch (Throwable) {
            return false;
        }
    }

    private function restoreTableExists(): bool {
        try {
            return DB::getSchemaBuilder()->hasTable((new RestoreTest())->getTable());
        } catch (Throwable) {
            return false;
        }
    }
}
