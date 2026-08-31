<?php
/*
 * Created on   : Sun Aug 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MergeCoverageRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Services\{AbstractEntityMergeService, ArticleMergeService, AssetMergeService, CustomerMergeService, ProjectMergeService, SupplierMergeService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Gate zu S-33 (Sicherheitsscan 2026-08-23).
 *
 * Die Merge-Dienste hängen abhängige Zeilen auf das Ziel um und löschen die
 * Quelle danach **hart** — keines der fünf Modelle nutzt SoftDeletes. Wird eine
 * Tabelle dabei übersehen, reißt `ON DELETE CASCADE` sie ersatzlos mit; bei
 * `SET NULL` bleibt sie zwar stehen, verliert aber ihren Bezug. Beides
 * geschieht ohne Fehlermeldung.
 *
 * Genau das war der Zustand: 79 Tabellen führten die FK-Spalte, ohne von den
 * handgepflegten Positivlisten erfasst zu sein — 22 davon mit CASCADE.
 *
 * Seither leitet {@see AbstractEntityMergeService::scalarTables()} die Liste
 * aus dem Schema ab. Dieser Test hält den Umkehrschluss fest: **jede** Tabelle
 * mit der FK-Spalte muss von genau einem Weg erfasst sein, und die einzige
 * Liste, die es noch gibt, ist eine Ausnahmeliste mit Begründung.
 */
class MergeCoverageRuleTest extends TestCase {
    use RefreshDatabase;

    /** @return array<string, array{0: class-string<AbstractEntityMergeService>, 1: string}> */
    public static function mergeServices(): array {
        return [
            'Kunde' => [CustomerMergeService::class, 'customer_id'],
            'Lieferant' => [SupplierMergeService::class, 'supplier_id'],
            'Anlage' => [AssetMergeService::class, 'asset_id'],
            'Artikel' => [ArticleMergeService::class, 'article_id'],
            'Projekt' => [ProjectMergeService::class, 'project_id'],
        ];
    }

    /**
     * @param  class-string<AbstractEntityMergeService>  $serviceClass
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('mergeServices')]
    public function test_jede_tabelle_mit_fremdschluessel_wird_behandelt(string $serviceClass, string $fk): void {
        $service = app($serviceClass);

        $handled = array_merge(
            $this->invokeProtected($service, 'scalarTables'),
            array_keys($this->invokeProtected($service, 'pivotTables')),
            array_keys($this->invokeProtected($service, 'skippedTables')),
            $this->invokeProtected($service, 'separatelyHandledTables'),
        );

        $missing = array_values(array_diff(AbstractEntityMergeService::tablesWithColumn($fk), $handled));

        $this->assertSame([], $missing, sprintf(
            "%s lässt Tabellen mit %s unbehandelt — beim Merge gehen deren Zeilen verloren:\n  %s",
            class_basename($serviceClass),
            $fk,
            implode("\n  ", $missing),
        ));
    }

    /**
     * Eine Ausnahme ohne Begründung ist ein Vergessen mit Alibi: der Wert der
     * Liste hängt daran, dass jemand aufschreibt, warum eine Tabelle *nicht*
     * mitwandern soll.
     *
     * @param  class-string<AbstractEntityMergeService>  $serviceClass
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('mergeServices')]
    public function test_ausnahmen_sind_begruendet_und_existieren(string $serviceClass, string $fk): void {
        $service = app($serviceClass);

        /** @var array<string, string> $skipped */
        $skipped = $this->invokeProtected($service, 'skippedTables');

        // Eine leere Ausnahmeliste ist der erwünschte Normalfall: beim Merge
        // soll grundsätzlich alles mitwandern.
        $this->assertLessThan(10, count($skipped), "{$serviceClass}: so viele Ausnahmen sind wieder eine Positivliste.");

        foreach ($skipped as $table => $reason) {
            $this->assertTrue(Schema::hasTable($table), "{$serviceClass}: Ausnahme {$table} existiert nicht (mehr).");
            $this->assertTrue(Schema::hasColumn($table, $fk), "{$serviceClass}: Ausnahme {$table} führt kein {$fk}.");
            $this->assertGreaterThan(20, mb_strlen(trim($reason)), "{$serviceClass}: Ausnahme {$table} ist nicht begründet.");
        }
    }

    /**
     * Kollidiert das Umhängen mit einem zusammengesetzten Unique-Index, muss
     * das aufgelöst (Pivot/Uniquify) oder gemeldet werden — nie in einen rohen
     * SQL-Fehler 1062 laufen. Der Guard deckt das generisch ab; dieser Test
     * hält fest, dass er die betroffenen Tabellen überhaupt zu sehen bekommt.
     *
     * @param  class-string<AbstractEntityMergeService>  $serviceClass
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('mergeServices')]
    public function test_zusammengesetzte_unique_indizes_sind_abgedeckt(string $serviceClass, string $fk): void {
        $service = app($serviceClass);

        $guarded = array_merge(
            $this->invokeProtected($service, 'scalarTables'),          // Guard prüft vorab
            array_keys($this->invokeProtected($service, 'pivotTables')), // löst selbst auf
            array_keys($this->invokeProtected($service, 'skippedTables')),
            $this->invokeProtected($service, 'separatelyHandledTables'), // Uniquify-Schritt
        );

        foreach (AbstractEntityMergeService::tablesWithColumn($fk) as $table) {
            foreach (Schema::getIndexes($table) as $index) {
                if (($index['unique'] ?? false) !== true) {
                    continue;
                }
                $columns = array_values($index['columns'] ?? []);
                if (count($columns) < 2 || ! in_array($fk, $columns, true)) {
                    continue;
                }

                $this->assertContains($table, $guarded, sprintf(
                    '%s: %s hat UNIQUE(%s), wird aber von keinem Weg erfasst.',
                    class_basename($serviceClass),
                    $table,
                    implode(', ', $columns),
                ));
            }
        }
    }

    /** @return array<mixed> */
    private function invokeProtected(AbstractEntityMergeService $service, string $method): array {
        $reflection = new ReflectionMethod($service, $method);
        $reflection->setAccessible(true);

        /** @var array<mixed> $result */
        $result = $reflection->invoke($service);

        return $result;
    }
}
