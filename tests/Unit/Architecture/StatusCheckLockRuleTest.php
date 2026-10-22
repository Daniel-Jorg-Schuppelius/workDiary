<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StatusCheckLockRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „Sperre nehmen, unter der Sperre neu prüfen“ (Sicherheitsaudit
 * 2026-10-21, li-1/li-4/li-7): Ein Dienst, der einen Statusübergang prüft und
 * danach schreibt, nimmt im selben Methodenrumpf eine Sperre
 * (`lockForUpdate`, `Cache::lock`, `->block(`, `lockAndAssert`) — sonst prüfen
 * zwei gleichzeitige Aufrufe denselben alten Stand und schreiben beide. Das
 * Muster war im Oktober 2026 dreimal neu entstanden (Belegfestschreibung,
 * Zuordnung aufheben, Tourvorschlag). Methoden mit der Endung `Locked` laufen
 * per Konvention nur unter der Sperre ihres Aufrufers. Der Bestand steht in
 * `baselines/status-check-lock.php` und darf nur schrumpfen.
 */
final class StatusCheckLockRuleTest extends TestCase {
    use ScansSourceTree;

    private const CHECK = '/(canTransitionTo\(|assertStatusTransition\(|assertValidatedTransition\(|assertWorkflowTransition\(|ensureTransition\()/';

    private const WRITE = '/(->update\(|->save\(\)|forceFill\(|->delete\(\))/';

    private const LOCK = '/(lockForUpdate|Cache::lock|->block\(|lockAndAssert|lockAndRefresh|sharedLock)/';

    public function test_status_checks_followed_by_writes_take_a_lock(): void {
        /** @var list<string> $baseline */
        $baseline = require __DIR__ . '/baselines/status-check-lock.php';
        $new = array_values(array_diff($this->offenders(), $baseline));

        $this->assertSame([], $new, "Statusprüfung mit anschließendem Schreiben ohne Sperre:\n  " . implode("\n  ", $new)
            . "\n\nIm selben Methodenrumpf sperren und unter der Sperre neu lesen, etwa\n"
            . "DB::transaction(fn () => X::query()->whereKey(\$id)->lockForUpdate()->firstOrFail() …) oder\n"
            . "Cache::lock(…)->block(…, fn () => …) bei Wegen mit Fremdsystem (Sicherheitsaudit 2026-10-21, li-1).");
    }

    public function test_baseline_has_no_stale_entries(): void {
        /** @var list<string> $baseline */
        $baseline = require __DIR__ . '/baselines/status-check-lock.php';
        $stale = array_values(array_diff($baseline, $this->offenders()));

        $this->assertSame([], $stale, "Diese Einträge sperren inzwischen oder gibt es nicht mehr — aus der Baseline streichen:\n  " . implode("\n  ", $stale));
    }

    public function test_the_scan_sees_services(): void {
        $this->assertGreaterThan(500, count($this->scannedFiles()));
    }

    /** @return list<string> Datei::Methode */
    private function offenders(): array {
        $found = [];
        foreach ($this->scannedFiles() as $path) {
            $relative = $this->relativePath($path);
            foreach ($this->methodBodies((string) file_get_contents($path)) as $name => $body) {
                if (str_ends_with($name, 'Locked')) {
                    continue;
                }
                if (preg_match(self::CHECK, $body, $check, PREG_OFFSET_CAPTURE) === 1
                    && preg_match(self::WRITE, $body, $write, PREG_OFFSET_CAPTURE, $check[0][1]) === 1
                    && preg_match(self::LOCK, $body) !== 1) {
                    $found[] = $relative . '::' . $name;
                }
            }
        }
        sort($found);

        return array_values(array_unique($found));
    }

    /** @return list<string> */
    private function scannedFiles(): array {
        $files = $this->phpFiles('app/Services');
        foreach ($this->phpFiles('app/Plugins') as $path) {
            if (str_contains($path, DIRECTORY_SEPARATOR . 'Services' . DIRECTORY_SEPARATOR)) {
                $files[] = $path;
            }
        }

        return $files;
    }

    /** @return array<string, string> Methodenname => Rumpf ohne Kommentare */
    private function methodBodies(string $source): array {
        $tokens = token_get_all($source);
        $count = count($tokens);
        $bodies = [];
        for ($i = 0; $i < $count; $i++) {
            if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
                continue;
            }
            $name = null;
            for ($j = $i + 1; $j < $count && $j < $i + 4; $j++) {
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                    $name = $tokens[$j][1];

                    break;
                }
            }
            $depth = 0;
            $started = false;
            $body = '';
            for ($k = $i + 1; $k < $count; $k++) {
                $token = $tokens[$k];
                $text = is_array($token) ? $token[1] : $token;
                if (! $started) {
                    if ($text === '{') {
                        $started = true;
                        $depth = 1;
                    } elseif ($text === ';') {
                        break;
                    }

                    continue;
                }
                if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                if ($text === '{') {
                    $depth++;
                } elseif ($text === '}' && --$depth === 0) {
                    break;
                }
                $body .= $text;
            }
            if ($name !== null && $body !== '') {
                $bodies[$name] = $body;
            }
            $i = $k;
        }

        return $bodies;
    }
}
