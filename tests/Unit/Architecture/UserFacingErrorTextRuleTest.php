<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserFacingErrorTextRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate (MVP-827): In der HTTP-Schicht gelangt der Text einer
 * allgemein gefangenen Exception (Throwable, RuntimeException, Transport-/SDK-
 * Ausnahmen …) nur über App\Support\ErrorText::for() zum Nutzer — sonst standen
 * cURL-Fehler mit internen Hosts in Meldungen. Protokollaufrufe dürfen den Text
 * weiter nutzen; Fachklassen (eigene Exceptions) sind nicht betroffen.
 */
class UserFacingErrorTextRuleTest extends TestCase {
    use ScansSourceTree;

    private const GENERIC = [
        'Throwable', 'Exception', 'RuntimeException', 'InvalidArgumentException', 'LogicException', 'ErrorException',
        'DomainException', 'UnexpectedValueException', 'GuzzleException', 'TransferException', 'RequestException',
        'ConnectException', 'ClientException', 'ServerException', 'JsonException', 'ValueError', 'TypeError',
    ];

    /** @var array<string, string> Pfad → Begründung */
    private const ALLOW_LIST = [
        'app/Http/Controllers/Admin/DiagnosticsController.php' => 'Diagnose für Betreiber: der technische Fehler ist dort der Zweck',
    ];

    public function test_generic_exception_messages_reach_users_only_through_error_text(): void {
        $violations = [];

        foreach ([...$this->phpFiles('app/Http'), ...$this->phpFiles('app/Livewire'), ...$this->phpFiles('app/Plugins')] as $file) {
            $relative = $this->relativePath($file);
            if ((! str_contains($relative, '/Http/') && ! str_contains($relative, '/Livewire/')) || $this->isAllowListed($relative, self::ALLOW_LIST)) {
                continue;
            }
            $source = (string) file_get_contents($file);
            preg_match_all('~catch \(([^)]+?)\s+(\$\w+)\)\s*\{~', $source, $catches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
            foreach ($catches as $catch) {
                if (! $this->isGeneric($catch[1][0])) {
                    continue;
                }
                $var = $catch[2][0];
                $blockStart = $catch[0][1] + strlen($catch[0][0]);
                $block = substr($source, $blockStart, $this->blockLength($source, $blockStart));
                $offset = 0;
                while (($pos = strpos($block, $var . '->getMessage()', $offset)) !== false) {
                    $offset = $pos + 1;
                    $absolute = $blockStart + $pos;
                    $statement = $this->statementBefore($source, $absolute);
                    if (preg_match('~^\s*(\\\\?Illuminate\\\\Support\\\\Facades\\\\Log::|Log::|logger\(|report\()~', $statement) === 1 || str_contains($statement, 'markFailed(')) {
                        continue;
                    }
                    $violations[] = sprintf('%s:%d', $relative, $this->lineOf($source, $absolute));
                }
            }
        }

        $this->assertSame([], $violations, "getMessage() einer allgemein gefangenen Exception in einer Nutzermeldung — ErrorText::for(\$e) verwenden:\n"
            . implode("\n", $violations));
    }

    private function isGeneric(string $types): bool {
        foreach (explode('|', $types) as $type) {
            $short = substr((string) strrchr('\\' . trim($type), '\\'), 1);
            if (in_array($short, self::GENERIC, true) || str_ends_with($short, 'ApiException')) {
                return true;
            }
        }

        return false;
    }

    private function blockLength(string $source, int $start): int {
        $depth = 1;
        for ($i = $start, $n = strlen($source); $i < $n; $i++) {
            $depth += match ($source[$i]) {
                '{' => 1,
                '}' => -1,
                default => 0,
            };
            if ($depth === 0) {
                return $i - $start;
            }
        }

        return strlen($source) - $start;
    }

    private function statementBefore(string $source, int $position): string {
        $start = max((int) strrpos(substr($source, 0, $position), ';'), (int) strrpos(substr($source, 0, $position), '{'), (int) strrpos(substr($source, 0, $position), '}')) + 1;

        return substr($source, $start, $position - $start);
    }
}
