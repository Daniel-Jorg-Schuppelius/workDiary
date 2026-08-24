<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppendOnlyCascadeRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate für Nachweis-Tabellen (Vollscan 2026-08-23, F4): Der
 * AppendOnly-Guard wirkt nur auf Eloquent-Events — ein ON DELETE CASCADE von
 * users auf protocol_events/month_closure_events/disposal_job_events/
 * document_versions löscht Nachweise per DB-Kaskade am Guard vorbei, sobald
 * ein User hart gelöscht wird. audit_logs/stock_movements/cash_entries nutzen
 * SET NULL für Akteur-FKs — das ist der Standard.
 *
 * Regel: Tabellen von Modellen mit AppendOnly/HashChained tragen keinen
 * CASCADE-FK auf users.
 */
class AppendOnlyCascadeRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> Tabelle → Nachzieh-Welle */
    private const ALLOW_LIST = [
        'protocol_events' => 'Welle 3 (F4): actor_user_id → nullOnDelete.',
        'month_closure_events' => 'Welle 3 (F4).',
        'disposal_job_events' => 'Welle 3 (F4).',
        'document_versions' => 'Welle 3 (F4): uploaded_by_user_id → nullOnDelete.',
    ];

    public function test_append_only_tables_do_not_cascade_from_users(): void {
        $tables = $this->schemaTables();
        $violations = [];

        foreach ($this->modelClasses() as $class) {
            $traits = class_uses_recursive($class);
            if (! isset($traits['App\Models\Concerns\AppendOnly']) && ! isset($traits['App\Models\Concerns\HashChained'])) {
                continue;
            }

            $table = $this->tableOfModel($class);
            if ($table === '' || isset(self::ALLOW_LIST[$table]) || ! isset($tables[$table])) {
                continue;
            }

            foreach ($tables[$table]['foreign'] as $column => $fk) {
                if ($fk['references'] === 'users' && $fk['on_delete'] === 'CASCADE') {
                    $violations[] = sprintf('%s.%s — ON DELETE CASCADE auf users (%s)', $table, $column, $class);
                }
            }
        }

        sort($violations);

        $this->assertSame([], $violations, "Append-only-Tabelle mit CASCADE-FK auf users — Nachweise verschwinden beim User-Löschen.\n"
            . "nullOnDelete()/restrictOnDelete() verwenden (Muster audit_logs).\n\n" . implode("\n", $violations));
    }
}
