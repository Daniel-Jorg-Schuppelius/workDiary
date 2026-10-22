<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PartyDeletionCascadeRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Services\Stammdaten\PartyDeletionGuard;
use PHPUnit\Framework\TestCase;

/**
 * Jede Kaskade (ON DELETE CASCADE) auf `customers` und `suppliers` ist in
 * PartyDeletionGuard eingeordnet: als Beleg, der das Löschen sperrt, oder als
 * Zubehör, das mitgehen darf. Anlass: Ein gelöschter Kunde nahm seine
 * ausgestellten Rechnungen per Fremdschlüssel mit (Vollscan 2026-10-21).
 */
final class PartyDeletionCascadeRuleTest extends TestCase {
    private const ROOT = __DIR__ . '/../../..';

    public function test_every_cascade_onto_a_party_is_classified(): void {
        $cascades = $this->cascadesFromDump();
        foreach ($this->cascadesFromNewerMigrations() as $party => $tables) {
            $cascades[$party] = array_values(array_unique([...($cascades[$party] ?? []), ...$tables]));
        }

        $problems = [];
        foreach (['customers', 'suppliers'] as $party) {
            $classified = [...PartyDeletionGuard::BLOCKING[$party], ...PartyDeletionGuard::DELETED_WITH_PARTY[$party]];
            foreach ($cascades[$party] ?? [] as $table) {
                if (! in_array($table, $classified, true)) {
                    $problems[] = "{$table} → {$party}: neue Kaskade, in PartyDeletionGuard::BLOCKING oder DELETED_WITH_PARTY einordnen";
                }
            }
            foreach ($classified as $table) {
                if (! in_array($table, $cascades[$party] ?? [], true)) {
                    $problems[] = "{$table} → {$party}: steht in PartyDeletionGuard, hat aber keine Kaskade (mehr)";
                }
            }
            $twice = array_intersect(PartyDeletionGuard::BLOCKING[$party], PartyDeletionGuard::DELETED_WITH_PARTY[$party]);
            foreach ($twice as $table) {
                $problems[] = "{$table} → {$party}: steht in beiden Listen";
            }
        }
        sort($problems);

        $this->assertSame([], $problems, implode("\n", $problems));
    }

    public function test_blocking_tables_resolve_to_models(): void {
        $aliases = (require self::ROOT . '/config/morph-map.php')['aliases'];
        $missing = [];
        foreach (PartyDeletionGuard::BLOCKING as $party => $tables) {
            foreach ($tables as $table) {
                if (! isset($aliases[$table])) {
                    $missing[] = "{$party}: {$table}";
                }
            }
        }

        $this->assertSame([], $missing, 'Belegtabelle ohne Modell in der Morph-Map');
    }

    /** @return array<string, list<string>> Partei → Tabellen mit Kaskade */
    private function cascadesFromDump(): array {
        $sql = (string) file_get_contents(self::ROOT . '/database/schema/mysql-schema.sql');
        $result = [];
        $table = null;
        foreach (explode("\n", $sql) as $line) {
            if (preg_match('/^CREATE TABLE `([a-z0-9_]+)`/', $line, $m) === 1) {
                $table = $m[1];
            } elseif ($table !== null && preg_match('/REFERENCES `(customers|suppliers)` \(`id`\) ON DELETE CASCADE/', $line, $m) === 1) {
                $result[$m[1]][] = $table;
            }
        }

        return array_map(static fn (array $tables): array => array_values(array_unique($tables)), $result);
    }

    /**
     * Migrationen jünger als der Dump: Kaskaden grob aus dem Quelltext.
     *
     * @return array<string, list<string>>
     */
    private function cascadesFromNewerMigrations(): array {
        $sql = (string) file_get_contents(self::ROOT . '/database/schema/mysql-schema.sql');
        preg_match_all("/'(\\d{4}_\\d{2}_\\d{2}_\\d{6}_[a-z0-9_]+)'/", $sql, $m);
        $dumped = array_flip($m[1]);
        $files = [...(glob(self::ROOT . '/database/migrations/*.php') ?: []), ...(glob(self::ROOT . '/app/Plugins/*/Database/Migrations/*.php') ?: [])];

        $result = [];
        foreach ($files as $file) {
            if (isset($dumped[basename($file, '.php')])) {
                continue;
            }
            $code = (string) file_get_contents($file);
            if (preg_match_all("/Schema::(?:create|table)\\('([a-z0-9_]+)'.*?\\}\\);/s", $code, $blocks, PREG_SET_ORDER) === 0) {
                continue;
            }
            foreach ($blocks as $block) {
                foreach (['customers', 'suppliers'] as $party) {
                    $references = preg_match("/constrained\\('{$party}'\\)|->on\\('{$party}'\\)|foreignIdFor\\([^)]*" . ($party === 'customers' ? 'Customer' : 'Supplier') . '::class/', $block[0]) === 1;
                    if ($references && preg_match('/cascadeOnDelete\(\)|onDelete\(\'cascade\'\)/', $block[0]) === 1) {
                        $result[$party][] = $block[1];
                    }
                }
            }
        }

        return $result;
    }
}
