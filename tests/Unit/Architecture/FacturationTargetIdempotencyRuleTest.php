<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FacturationTargetIdempotencyRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Services\Finance\Targets\Concerns\ReconcilesByMarker;
use App\Services\Finance\Targets\{FacturationTarget, FileTarget};
use Tests\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Gate zum Konsolidierungs-Audit 2026-10 (k2-01): ein Übergabeziel, das im
 * Fremdsystem einen Beleg anlegt, prüft vorher den bestehenden Nachweis
 * ({@see ReconcilesByMarker}). Das Lexoffice-Ziel tat das als einziges nicht —
 * ein zweiter Klick nach einem Abbruch legte einen zweiten Entwurf an.
 */
class FacturationTargetIdempotencyRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<class-string, string> Klasse => Grund */
    private const ALLOWED = [
        FileTarget::class => 'Schreibt ein Übergabepaket in den eigenen Speicher — kein Fremdsystem, keine Dublette möglich.',
    ];

    public function test_remote_transfer_targets_check_the_existing_reference(): void {
        $violations = [];
        $targets = 0;
        foreach ([...$this->phpFiles('app/Plugins'), ...$this->phpFiles('app/Services/Finance/Targets')] as $file) {
            $source = (string) file_get_contents($file);
            if (preg_match('/\bclass\s+(\w+)[^{]*\bimplements\b[^{]*\bFacturationTarget\b/', $source, $match) !== 1
                || preg_match('/^namespace\s+([^;]+);/m', $source, $namespace) !== 1) {
                continue;
            }
            $class = $namespace[1] . '\\' . $match[1];
            if (! is_subclass_of($class, FacturationTarget::class)) {
                continue;
            }
            $targets++;
            if (isset(self::ALLOWED[$class])) {
                continue;
            }
            if (! in_array(ReconcilesByMarker::class, class_uses_recursive($class), true) || ! str_contains($source, '$this->existingReference(')) {
                $violations[] = $class;
            }
        }

        $this->assertGreaterThanOrEqual(5, $targets, 'Die Suche findet die Übergabeziele nicht mehr.');
        $this->assertSame([], $violations, "Übergabeziel ohne Prüfung des bestehenden Nachweises (ReconcilesByMarker::existingReference):\n" . implode("\n", $violations));
    }
}
