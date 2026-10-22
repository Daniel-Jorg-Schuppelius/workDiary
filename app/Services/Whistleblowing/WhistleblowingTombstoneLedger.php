<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WhistleblowingTombstoneLedger.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Whistleblowing;

use App\Models\Whistleblowing\CaseTombstone;
use CommonToolkit\Helper\Data\JsonHelper;
use CommonToolkit\Helper\FileSystem\{File, Folder};

/**
 * Der überlebende Tombstone-Ledger (Konzept Hinweisgebersystem §16): jede
 * Löschung wird zusätzlich als Zeile in eine Datei außerhalb der Datenbank
 * geschrieben. Spielt ein Restore einen älteren Stand zurück, stellt
 * `whistleblowing:apply-tombstones` die Löschungen danach wieder her.
 * Der Pfad gehört außerhalb des wiederhergestellten Baums
 * (`WHISTLEBLOWING_TOMBSTONE_LEDGER`), sonst überschreibt ihn der Restore.
 */
final class WhistleblowingTombstoneLedger {
    public function path(): string {
        return (string) config('whistleblowing.tombstone_ledger');
    }

    public function append(CaseTombstone $tombstone): void {
        $path = $this->path();
        $directory = dirname($path);
        if (! Folder::exists($directory)) {
            Folder::create($directory, 0700, true);
        }
        File::append($path, JsonHelper::encode([
            'organization_id' => (int) $tombstone->getAttribute('organization_id'),
            'case_number' => (string) $tombstone->getAttribute('case_number'),
            'public_id' => (string) $tombstone->getAttribute('public_id'),
            'period_from' => $tombstone->getAttribute('period_from')?->toDateString(),
            'period_to' => $tombstone->getAttribute('period_to')?->toDateString(),
            'deleted_at' => $tombstone->getAttribute('deleted_at')?->toIso8601String(),
            'audit_hash' => (string) $tombstone->getAttribute('audit_hash'),
        ], JSON_UNESCAPED_UNICODE) . "\n");
    }

    /**
     * @return list<array{organization_id: int, case_number: string, public_id: string, period_from: ?string, period_to: ?string, deleted_at: ?string, audit_hash: string}>
     */
    public function entries(?string $path = null): array {
        $path ??= $this->path();
        if (! File::exists($path)) {
            return [];
        }
        $entries = [];
        foreach (preg_split('/\r\n|\n/', File::read($path)) ?: [] as $line) {
            if (trim($line) === '' || ! JsonHelper::isValid($line)) {
                continue;
            }
            $row = JsonHelper::decode($line);
            if (! is_array($row) || ! isset($row['organization_id'], $row['public_id'], $row['case_number'])) {
                continue;
            }
            $entries[] = [
                'organization_id' => (int) $row['organization_id'],
                'case_number' => (string) $row['case_number'],
                'public_id' => (string) $row['public_id'],
                'period_from' => isset($row['period_from']) ? (string) $row['period_from'] : null,
                'period_to' => isset($row['period_to']) ? (string) $row['period_to'] : null,
                'deleted_at' => isset($row['deleted_at']) ? (string) $row['deleted_at'] : null,
                'audit_hash' => (string) ($row['audit_hash'] ?? ''),
            ];
        }

        return $entries;
    }
}
