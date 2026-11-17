<?php
/*
 * Created on   : Tue Nov 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StockCorrectionRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Gate MVP-1739: Korrekturen des physischen Eigenbestands sind bewertet und
 * laufen über den Baustein `App\Services\Inventory\StockCorrection` (MVP-1727).
 * In `app/` gibt es daher keinen Aufruf von `InventoryLedger::correction()` und
 * keine `StockMovementType::Correction`-Buchung über `->post(` — ausgenommen
 * wertneutrale Zustands- und Chargenbuchungen mit Grund. Erkannt wird die
 * Buchung, deren Anweisung den Fall nennt; ein über eine Variable gereichter
 * Typ fällt durch das Raster.
 */
final class StockCorrectionRuleTest extends TestCase {
    use ScansSourceTree;

    /**
     * Datei → [erlaubte Fundstellen, Grund].
     *
     * @var array<string, array{0: int, 1: string}>
     */
    private const array ALLOWED = [
        'app/Services/Inventory/InventoryLedger.php' => [1, 'Definition des unbewerteten Grundbausteins correction() — die App ruft ihn nicht auf.'],
        'app/Services/Inventory/LotService.php' => [1, 'detach(): Paar −mit Charge/+ohne Charge im selben Topf; Menge und Wert des Lagers bleiben (Reparaturbefehl).'],
        'app/Services/Inventory/Claims/InventoryRmaStockHandler.php' => [1, 'restock-out: Abgang aus dem Quarantäne-Zustand, der nicht im Bewertungsbuch steht; der Zugang restock-in läuft über StockCorrection.'],
    ];

    public function test_corrections_of_physical_own_stock_go_through_stock_correction(): void {
        $violations = [];
        foreach ($this->phpFiles('app') as $file) {
            $relative = $this->relativePath($file);
            $source = (string) file_get_contents($file);
            $offsets = $this->violationsIn($source);
            if (count($offsets) <= (self::ALLOWED[$relative][0] ?? 0)) {
                continue;
            }
            foreach ($offsets as $offset) {
                $violations[] = sprintf('%s:%d', $relative, $this->lineOf($this->withoutComments($source), $offset));
            }
        }

        $this->assertSame([], $violations, "Unbewertete Korrektur des Lagerbestands — über StockCorrection::book() buchen:\n" . implode("\n", $violations));
    }

    /** Die Ausnahmen decken genau ihre Fundstellen, sonst wären sie Ballast oder verdeckten eine neue. */
    public function test_every_exception_still_matches_its_count(): void {
        $stale = [];
        foreach (self::ALLOWED as $relative => [$count]) {
            $path = $this->repoRoot() . '/' . $relative;
            $source = is_file($path) ? (string) file_get_contents($path) : '';
            $found = count($this->violationsIn($source));
            if ($found !== $count) {
                $stale[] = sprintf('%s: erlaubt %d, gefunden %d', $relative, $count, $found);
            }
        }

        $this->assertSame([], $stale, "Ausnahme passt nicht mehr — Zahl anpassen oder streichen:\n" . implode("\n", $stale));
    }

    public function test_the_rule_recognises_unvalued_corrections(): void {
        $this->assertCount(1, $this->violationsIn("<?php\n\$this->ledger->correction(\$variant, \$warehouse, StockState::Physical, '-2');"));
        $this->assertCount(1, $this->violationsIn("<?php\nforeach (\$pots as \$pot) {\n    \$this->ledger->post(new StockPosting(\n        \$variant,\n        \$warehouse,\n        StockState::Physical,\n        '1',\n        StockMovementType::Correction,\n    ));\n}"));
        $this->assertCount(0, $this->violationsIn("<?php\n\$x = 1;\nreturn \$this->corrections->book(new StockPosting(\$variant, \$warehouse, StockState::Physical, '1', StockMovementType::Correction), \$cost);"));
        $this->assertCount(0, $this->violationsIn("<?php\nif (in_array(\$posting->type, [StockMovementType::Correction, StockMovementType::LotBlock], true)) {\n    return;\n}"));
        $this->assertCount(0, $this->violationsIn("<?php\npublic function correction(ArticleVariant \$variant): StockMovement {\n    return \$this->post(\$posting);\n}"));
        // Kommentare zählen nicht.
        $this->assertCount(0, $this->violationsIn("<?php\n// \$this->ledger->correction(\$variant, \$warehouse, \$state, '1');"));
    }

    /**
     * Offsets (im kommentarfreien Text) der unbewerteten Korrekturen einer Datei.
     *
     * @return list<int>
     */
    private function violationsIn(string $source): array {
        $source = $this->withoutComments($source);
        $offsets = [];
        if (preg_match_all('/->correction\s*\(/', $source, $calls, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($calls[0] as [, $offset]) {
                $offsets[] = (int) $offset;
            }
        }
        if (preg_match_all('/\bStockMovementType::Correction\b/', $source, $cases, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($cases[0] as [, $offset]) {
                $statement = $this->statementUpTo($source, (int) $offset);
                if (preg_match('/->post\s*\(/', $statement) === 1 && preg_match('/->book\s*\(/', $statement) !== 1) {
                    $offsets[] = (int) $offset;
                }
            }
        }
        sort($offsets);

        return $offsets;
    }

    /** Kommentare entfernen, Zeilenumbrüche behalten — die Zeilennummern bleiben richtig. */
    private function withoutComments(string $source): string {
        $source = (string) preg_replace_callback('~/\*.*?\*/~s', static fn (array $m): string => str_repeat("\n", substr_count($m[0], "\n")), $source);

        return (string) preg_replace('~^\s*(//|#(?!\[)).*$~m', '', $source);
    }

    /** Anweisung bis zur Fundstelle: ab dem letzten `;`, `{` oder `}` davor. */
    private function statementUpTo(string $source, int $offset): string {
        $before = substr($source, 0, $offset);
        $start = max((int) strrpos($before, ';'), (int) strrpos($before, '{'), (int) strrpos($before, '}'));

        return substr($before, $start);
    }
}
