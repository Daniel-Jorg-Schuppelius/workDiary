<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RawRequestDateParseRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „kein Roh-Carbon::parse auf Request-Input im HTTP-Layer"
 * (Vollscan 2026-08-23, B10): Hand-editierte Bookmarks (`?from=Müll`) endeten
 * als ungefangene InvalidFormatException → HTTP 500. Der Weg ist
 * `ResolvesGlobalDateRange` (resolveRange/resolveRangeWithDefault/
 * resolveNamedRangeWithDefault/resolveDateParam), ein lokaler try/catch mit
 * fachlichem Fallback oder eine `date`-Validierungsregel VOR dem Parse.
 */
class RawRequestDateParseRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> Pfad → Begründung */
    private const ALLOW_LIST = [
        'app/Http/Controllers/Concerns/ResolvesGlobalDateRange.php' => 'der Guard selbst (parst innerhalb von try/catch bzw. dokumentierter Precedence)',
        'app/Http/Controllers/Admin/MaintenanceWindowController.php' => "inline \$request->validate(['ends_at' => 'date']) unmittelbar vor dem Parse",
    ];

    public function test_http_layer_guards_request_date_parsing(): void {
        $violations = [];
        foreach ($this->phpFiles('app/Http') as $file) {
            $relative = $this->relativePath($file);
            if ($this->isAllowListed($relative, self::ALLOW_LIST)) {
                continue;
            }
            $source = $this->stripComments((string) file_get_contents($file));

            if (preg_match_all('/Carbon(?:Immutable)?::parse\([^;)]{0,120}\$request->/s', $source, $matches, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }
            foreach ($matches[0] as $match) {
                // Ein try-Block direkt davor (≤ 300 Zeichen) gilt als Guard.
                $before = substr($source, max(0, (int) $match[1] - 300), 300);
                if (preg_match('/\btry\s*\{(?!.*\}\s*catch)/s', $before) === 1) {
                    continue;
                }
                $violations[] = sprintf('%s:%d', $relative, $this->lineOf($source, (int) $match[1]));
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "Roh-Carbon::parse auf Request-Input im HTTP-Layer — Müll-Input wird ein 500.\n"
            . "ResolvesGlobalDateRange nutzen (resolveDateParam/resolveNamedRangeWithDefault), try/catch mit fachlichem\n"
            . "Fallback oder date-Validierung davor (dann mit Begründung in die ALLOW_LIST).\n\n" . implode("\n", $violations));
    }
}
