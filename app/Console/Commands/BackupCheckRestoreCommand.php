<?php
/*
 * Created on   : Sat Nov 22 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupCheckRestoreCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands;

use App\Models\{AuditLog, BackupHeartbeat};
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Tägliche Plausibilitätsprüfung der Backup-Heartbeats (MVP-046 §6).
 *
 * Liest den jüngsten {@see BackupHeartbeat}, prüft Alter und Größe gegen
 * Schwellwerte aus `config/backup.php` und schreibt ein Audit-Event
 * `backup.checkRestore` mit dem Status (`ok`, `warn`, `critical`).
 *
 * Kein echter Restore-Drill — der Befehl kann von einer separaten,
 * externen Pipeline ergänzt werden (Spec: §6, "Heartbeat + Alter/Größe").
 *
 * Exit-Codes: 0 = ok, 1 = warn, 2 = critical.
 */
class BackupCheckRestoreCommand extends Command {
    protected $signature = 'workdiary:backup:check-restore '
        . '{--json : Ergebnis als JSON auf STDOUT ausgeben}';

    protected $description = 'Prüft Alter und Größe des letzten Backup-Heartbeats und protokolliert das Ergebnis.';

    public const STATUS_OK = 'ok';
    public const STATUS_WARN = 'warn';
    public const STATUS_CRITICAL = 'critical';

    public const EXIT_OK = 0;
    public const EXIT_WARN = 1;
    public const EXIT_CRITICAL = 2;

    public function handle(): int {
        $thresholds = (array) config('backup.thresholds_hours', ['warn' => 26, 'critical' => 72]);
        $warnHours = isset($thresholds['warn']) ? (int) $thresholds['warn'] : 26;
        $criticalHours = isset($thresholds['critical']) ? (int) $thresholds['critical'] : 72;
        $minSizeBytes = (int) config('backup.min_size_bytes', 0);
        $sizeDropRatio = (float) config('backup.size_drop_ratio', 0.5);

        $now = CarbonImmutable::now();
        $messages = [];
        $status = self::STATUS_OK;

        /** @var BackupHeartbeat|null $last */
        $last = BackupHeartbeat::query()->orderByDesc('occurred_at')->first();

        $lastAt = null;
        $ageHours = null;
        $sizeBytes = null;
        $manifestHash = null;
        $medianSizeBytes = null;

        if ($last === null) {
            $status = self::STATUS_CRITICAL;
            $messages[] = 'Kein Backup-Heartbeat vorhanden.';
        } else {
            $lastAt = $last->occurred_at;
            $sizeBytes = $last->size_bytes;
            $manifestHash = $last->manifest_hash;
            $ageHours = (int) CarbonImmutable::parse($lastAt)->diffInHours($now, true);

            if ($ageHours > $criticalHours) {
                $status = self::STATUS_CRITICAL;
                $messages[] = sprintf(
                    'Letztes Backup älter als %d Stunden (%d h).',
                    $criticalHours,
                    $ageHours
                );
            } elseif ($ageHours > $warnHours) {
                $status = self::worse($status, self::STATUS_WARN);
                $messages[] = sprintf(
                    'Letztes Backup älter als %d Stunden (%d h).',
                    $warnHours,
                    $ageHours
                );
            }

            if ($minSizeBytes > 0 && $sizeBytes !== null && $sizeBytes < $minSizeBytes) {
                $status = self::worse($status, self::STATUS_WARN);
                $messages[] = sprintf(
                    'Backup-Größe unter Mindestschwelle: %d < %d Bytes.',
                    (int) $sizeBytes,
                    $minSizeBytes
                );
            }

            $medianSizeBytes = $this->medianRecentSize($last->id);
            if (
                $medianSizeBytes !== null
                && $sizeBytes !== null
                && $sizeBytes > 0
                && $sizeBytes < (int) ($medianSizeBytes * $sizeDropRatio)
            ) {
                $status = self::worse($status, self::STATUS_WARN);
                $messages[] = sprintf(
                    'Backup-Größe um mehr als %d%% unter Median (%d < %d Bytes).',
                    (int) ((1 - $sizeDropRatio) * 100),
                    (int) $sizeBytes,
                    $medianSizeBytes
                );
            }
        }

        $payload = [
            'status' => $status,
            'last_backup_at' => $lastAt instanceof \DateTimeInterface ? $lastAt->format(\DateTimeInterface::ATOM) : null,
            'age_hours' => $ageHours,
            'size_bytes' => $sizeBytes,
            'manifest_hash' => $manifestHash,
            'median_size_bytes' => $medianSizeBytes,
            'thresholds_hours' => ['warn' => $warnHours, 'critical' => $criticalHours],
            'min_size_bytes' => $minSizeBytes,
            'size_drop_ratio' => $sizeDropRatio,
            'messages' => $messages,
            'checked_at' => $now->toIso8601String(),
        ];

        try {
            AuditLog::create([
                'organization_id' => null,
                'user_id' => null,
                'event' => 'backup.checkRestore',
                'auditable_type' => BackupHeartbeat::class,
                'auditable_id' => $last !== null ? $last->id : 0,
                'changes' => $payload,
            ]);
        } catch (Throwable $e) {
            // Audit-Fehler dürfen Exit-Code nicht verschleiern.
            $this->warn('Audit-Log konnte nicht geschrieben werden: ' . $e->getMessage());
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->line(sprintf('Backup-Check-Status: %s', $status));
            foreach ($messages as $msg) {
                $this->line(' - ' . $msg);
            }
        }

        return match ($status) {
            self::STATUS_CRITICAL => self::EXIT_CRITICAL,
            self::STATUS_WARN => self::EXIT_WARN,
            default => self::EXIT_OK,
        };
    }

    /**
     * Median der `size_bytes` der letzten N Heartbeats (ohne den aktuellen).
     */
    private function medianRecentSize(int $excludeId, int $window = 7): ?int {
        $sizes = BackupHeartbeat::query()
            ->where('id', '!=', $excludeId)
            ->whereNotNull('size_bytes')
            ->orderByDesc('occurred_at')
            ->limit($window)
            ->pluck('size_bytes')
            ->map(static fn($v) => (int) $v)
            ->sort()
            ->values()
            ->all();

        $count = count($sizes);
        if ($count === 0) {
            return null;
        }

        $middle = (int) ($count / 2);
        if ($count % 2 === 1) {
            return (int) $sizes[$middle];
        }

        return (int) (($sizes[$middle - 1] + $sizes[$middle]) / 2);
    }

    private static function worse(string $current, string $candidate): string {
        $rank = [self::STATUS_OK => 0, self::STATUS_WARN => 1, self::STATUS_CRITICAL => 2];

        return $rank[$candidate] > $rank[$current] ? $candidate : $current;
    }
}
