<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\ProcedureDocumentation\Sections;

use App\Models\Backup\{BackupGeneration, BackupTargetConnection};
use App\Models\{BackupHeartbeat, Organization};
use App\Services\Finance\ProcedureDocumentation\{FormatsSectionValues, ProcedureSection, SectionContext};

/**
 * Datensicherung (systemweit): Backup-Ziele OHNE Tokens (nur Anbieter/Name/
 * Status/Ablage), die jüngsten Generationen mit Verifikations- und
 * Restore-Test-Zeitpunkten (`workdiary:backup:verify`) und der letzte
 * Heartbeat. Schlüssel-Envelopes bleiben bewusst außen vor.
 */
final class BackupSection implements ProcedureSection {
    use FormatsSectionValues;

    public function key(): string {
        return 'backup';
    }

    public function title(): string {
        return (string) __('procedure-documentation.section.backup');
    }

    public function build(Organization $organization, SectionContext $context): array {
        $connections = [];
        foreach (BackupTargetConnection::query()->orderBy('name')->get() as $connection) {
            $connections[] = [
                $connection->provider->label(),
                $this->text($connection->name),
                $connection->status->label(),
                $this->text($connection->external_account_label),
                $this->text($connection->root_folder_ref),
                $this->quota($connection->quota_used, $connection->quota_total),
            ];
        }

        $generations = [];
        foreach (BackupGeneration::query()->orderByDesc('id')->limit(10)->get() as $generation) {
            $generations[] = [
                $generation->snapshot_uuid,
                $generation->status->label(),
                $this->dateTime($generation->committed_at),
                $this->dateTime($generation->last_verified_at),
                $this->dateTime($generation->restore_tested_at),
                $this->text($generation->manifest_sha256),
                $this->text($generation->app_version),
            ];
        }

        /** @var BackupHeartbeat|null $heartbeat */
        $heartbeat = BackupHeartbeat::query()->orderByDesc('occurred_at')->first();
        $heartbeatText = $heartbeat !== null
            ? $this->dateTime($heartbeat->occurred_at) . ' · ' . $this->text($heartbeat->source) . ($heartbeat->size_bytes !== null ? ' · ' . $heartbeat->size_bytes->format() : '')
            : (string) __('procedure-documentation.backup.no_heartbeat');

        return [
            'fields' => [
                'last_heartbeat' => $this->field('procedure-documentation.backup.last_heartbeat', $heartbeatText),
                'heartbeat_threshold' => $this->field('procedure-documentation.backup.heartbeat_threshold', (string) __('procedure-documentation.backup.hours', ['hours' => (int) config('backup.heartbeat_freshness_hours', 26)])),
                'command' => $this->field('procedure-documentation.backup.command', 'php artisan workdiary:backup:verify'),
            ],
            'tables' => [
                'connections' => [
                    'title' => (string) __('procedure-documentation.backup.table.connections'),
                    'columns' => [
                        (string) __('procedure-documentation.backup.col.provider'),
                        (string) __('procedure-documentation.backup.col.name'),
                        (string) __('procedure-documentation.backup.col.status'),
                        (string) __('procedure-documentation.backup.col.account'),
                        (string) __('procedure-documentation.backup.col.folder'),
                        (string) __('procedure-documentation.backup.col.quota'),
                    ],
                    'rows' => $connections,
                ],
                'generations' => [
                    'title' => (string) __('procedure-documentation.backup.table.generations'),
                    'columns' => [
                        (string) __('procedure-documentation.backup.col.snapshot'),
                        (string) __('procedure-documentation.backup.col.status'),
                        (string) __('procedure-documentation.backup.col.committed'),
                        (string) __('procedure-documentation.backup.col.verified'),
                        (string) __('procedure-documentation.backup.col.restore_tested'),
                        (string) __('procedure-documentation.backup.col.manifest'),
                        (string) __('procedure-documentation.backup.col.app_version'),
                    ],
                    'rows' => $generations,
                ],
            ],
            'notes' => [(string) __('procedure-documentation.backup.secrets_note')],
        ];
    }

    private function quota(?int $used, ?int $total): string {
        if ($used === null && $total === null) {
            return '—';
        }
        $format = static fn (?int $bytes): string => $bytes === null ? '—' : number_format($bytes / 1_073_741_824, 1, ',', '.') . ' GB';

        return $format($used) . ' / ' . $format($total);
    }
}
