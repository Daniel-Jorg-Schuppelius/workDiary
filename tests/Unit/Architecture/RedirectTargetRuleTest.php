<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RedirectTargetRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Support\UrlSafety;
use Tests\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Gate zum Sicherheitsaudit 2026-10-04 (xi-1): ein Rücksprungziel aus der
 * Eingabe prüft {@see UrlSafety::isSameOriginOrRelative()}. Der Vergleich
 * `str_starts_with($ziel, url('/'))` lässt `https://app.example.evil.tld`
 * und `https://app.example@evil.tld` durch.
 */
class RedirectTargetRuleTest extends TestCase {
    use ScansSourceTree;

    private const TARGET_KEYS = '_?(?:back|back_url|return|return_to|return_url|redirect|redirect_to|redirect_url|next)';

    /** @var array<string, string> Datei => Grund */
    private const ALLOWED = [
        'app/Http/Controllers/Time/TimeEntryController.php' => '`return_to` wird nur mit einem festen Wert verglichen und wählt zwischen zwei Routen — kein Ziel aus der Eingabe.',
    ];

    public function test_own_url_is_never_checked_by_prefix(): void {
        $violations = [];
        foreach ($this->phpFiles('app') as $file) {
            $source = $this->stripComments((string) file_get_contents($file));
            if (preg_match_all('/str_starts_with\([^;]*?,\s*url\(/', $source, $matches, PREG_OFFSET_CAPTURE) > 0) {
                foreach ($matches[0] as [$_, $offset]) {
                    $violations[] = $this->relativePath($file) . ':' . $this->lineOf($source, $offset);
                }
            }
        }

        $this->assertSame([], $violations, "Präfixvergleich gegen die eigene Adresse ist kein Schutz vor fremden Zielen — UrlSafety::isSameOriginOrRelative() nutzen:\n" . implode("\n", $violations));
    }

    public function test_return_targets_from_the_request_go_through_url_safety(): void {
        $violations = [];
        $seen = [];
        foreach ($this->phpFiles('app/Http') as $file) {
            $relative = $this->relativePath($file);
            $source = $this->stripComments((string) file_get_contents($file));
            if (preg_match('/->(?:input|query|get|post|string)\(\s*[\'"]' . self::TARGET_KEYS . '[\'"]/', $source) !== 1) {
                continue;
            }
            if (isset(self::ALLOWED[$relative])) {
                $seen[$relative] = true;

                continue;
            }
            if (! str_contains($source, 'UrlSafety::isSameOriginOrRelative(')) {
                $violations[] = $relative;
            }
        }

        $this->assertSame([], $violations, "Rücksprungziel aus der Eingabe ohne UrlSafety::isSameOriginOrRelative():\n" . implode("\n", $violations));
        $this->assertSame([], array_values(array_diff(array_keys(self::ALLOWED), array_keys($seen))), 'Veraltete Ausnahmen in ALLOWED.');
    }
}
