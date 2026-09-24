<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentLineContractRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Models\Contracts\{DocumentLine, HasDocumentLines};
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Gate MVP-865: Belegpositionen tragen {@see DocumentLine}, ihr Kopf
 * {@see HasDocumentLines}; Menge × Einzelpreis rechnet ausschließlich der
 * {@see \App\Services\Billing\DocumentTotalsCalculator}.
 */
class DocumentLineContractRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> Tabellen mit Menge, Preis und Steuersatz, die keine Belegposition sind */
    private const NOT_A_LINE = [
        'material_usages' => 'Quellposten der Zeiterfassung (wie TimeEntry): wird zur Rechnungsposition übernommen; der Zeilenbetrag läuft trotzdem über den Rechner.',
    ];

    /** @var array<string, string> Dateien, die Menge × Preis selbst multiplizieren dürfen */
    private const MULTIPLICATION_ALLOWED = [
        'app/Services/Billing/DocumentTotalsCalculator.php' => 'Die Rechenstelle selbst.',
    ];

    private const MULTIPLICATION = '~(?ix)
        (?: (?:quantity|qty|hours|units)\w*\)?\s*\*\s*\(?\s*(?:\(float\)\s*)?\$?[\w>-]*(?:unit_?price|price\b|rate\b) )
      | (?: (?:unit_?price|price|rate)\w*\)?\s*\*\s*\(?\s*(?:\(float\)\s*)?\$?[\w>-]*(?:quantity|qty|hours) )
      | (?: (?:unit_?price|price)\w*[^;\n]{0,40}->times\(\s*[^;)]*(?:quantity|qty) )
      | (?: bcmul\([^;]*(?:unit_?price|price)[^;]*(?:quantity|qty) )
    ~';

    public function test_tables_with_quantity_price_and_tax_have_a_document_line_model(): void {
        $modelsByTable = [];
        foreach ($this->modelClasses() as $class) {
            $modelsByTable[$this->tableOfModel($class)][] = $class;
        }

        $violations = [];
        foreach ($this->schemaTables() as $table => $definition) {
            $columns = $definition['columns'];
            if (! isset($columns['quantity'], $columns['unit_price']) || ! (isset($columns['tax_rate']) || isset($columns['vat_rate']))) {
                continue;
            }
            if (isset(self::NOT_A_LINE[$table])) {
                continue;
            }
            foreach ($modelsByTable[$table] ?? ["(kein Modell für {$table})"] as $class) {
                if (! is_subclass_of($class, DocumentLine::class)) {
                    $violations[] = "{$table}: {$class}";
                }
            }
        }
        sort($violations);

        $this->assertSame([], $violations, "Tabellen mit quantity/unit_price/tax_rate brauchen ein Modell mit DocumentLine (IsDocumentLine + lineColumns()) oder einen Eintrag in NOT_A_LINE mit Grund:\n" . implode("\n", $violations));
    }

    public function test_document_lines_belong_to_a_head_with_document_lines(): void {
        $violations = [];
        $seen = 0;
        foreach ($this->modelClasses() as $class) {
            if (! is_subclass_of($class, DocumentLine::class)) {
                continue;
            }
            $seen++;
            /** @var Model&DocumentLine $line */
            $line = new $class;
            $head = $line->lineDocument()->getRelated();
            if (! $head instanceof HasDocumentLines) {
                $violations[] = "{$class}: Kopf " . $head::class . ' implementiert HasDocumentLines nicht';
                continue;
            }
            if (! $head->lines()->getRelated() instanceof DocumentLine) {
                $violations[] = $head::class . '::lines() liefert keine DocumentLine';
            }
        }

        $this->assertGreaterThanOrEqual(7, $seen, 'Rechnung, Angebot, Rechnungsplan, Kostenermittlung, Übergabe (Quell- und Belegzeilen) und LV tragen den Vertrag.');
        $this->assertSame([], $violations, implode("\n", $violations));
    }

    public function test_quantity_times_price_is_only_computed_by_the_calculator(): void {
        $violations = [];
        foreach ($this->phpFiles('app') as $file) {
            $relative = $this->relativePath($file);
            if ($this->isAllowListed($relative, self::MULTIPLICATION_ALLOWED)) {
                continue;
            }
            $source = $this->stripComments((string) file_get_contents($file));
            if (preg_match_all(self::MULTIPLICATION, $source, $matches, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }
            foreach ($matches[0] as [$snippet, $offset]) {
                $violations[] = "{$relative}:{$this->lineOf($source, $offset)}: " . trim($snippet);
            }
        }

        $this->assertSame([], $violations, "Menge × Einzelpreis nur über DocumentTotalsCalculator::lineNet() (Rundung auf Preis-, dann Währungsskala); Ausnahmen mit Grund in MULTIPLICATION_ALLOWED:\n" . implode("\n", $violations));
    }
}
