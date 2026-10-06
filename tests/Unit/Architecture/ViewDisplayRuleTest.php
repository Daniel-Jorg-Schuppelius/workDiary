<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ViewDisplayRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use Tests\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Zwei Anzeige-Regeln aus dem Konsolidierungs-Audit 2026-10:
 *
 *  D1  Validierungsfehler zeigt `<x-validation-errors>` — kein handgebauter
 *      `@if ($errors->any())`-Block (k4-08: vier Markup-Dialekte). Seiten auf
 *      `layouts.app` ohne den Block bekommen ihre Fehler vom Layout.
 *  D2  Datum in Bildschirm-Views über `fdate()`/`fdatetime()` — kein
 *      `->format('d.m.Y…')` (k4-09: 403 Stellen ignorierten das eingestellte
 *      Format). PDF, Druck und Mail sind ausgenommen; der Rest steht in
 *      `baselines/date-format.php` und darf nur schrumpfen.
 *  D3  Ein Hinweisblock (`class="alert …"`) trägt `role`: Fehler und Warnung
 *      `alert`, sonst `status` (k4-13: 178 Blöcke ohne Rolle).
 */
class ViewDisplayRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> D1: Seiten mit eigenem Dokument und eigenem CSS */
    private const OWN_ERROR_MARKUP = [
        'resources/views/layouts/install.blade.php' => 'Installer-Layout ohne App-Komponenten.',
        'resources/views/privacy/public/portal.blade.php' => 'Öffentliches Auskunftsportal mit eigenem CSS.',
        'resources/views/careers/layout.blade.php' => 'Karriereportal mit eigenem CSS.',
        'resources/views/whistleblowing/public/mailbox_login.blade.php' => 'Hinweisgeberportal mit eigenem CSS, nur das Feld `secret`.',
        'resources/views/whistleblowing/public/portal.blade.php' => 'Hinweisgeberportal mit eigenem CSS.',
        'resources/views/licensing/required.blade.php' => 'Lizenzseite außerhalb von layouts.app.',
    ];

    private const NOT_A_SCREEN = '~^resources/views/(legacy|vendor|mail|emails|pdf|errors)/|/pdf/|/print/|(^|[/_-])pdf\.blade\.php$|[_-]pdf[_.-]|(^|[/_-])print\.blade\.php$|[_-]print[_.-]~';

    public function test_validation_errors_use_the_component(): void {
        $violations = [];
        $seen = [];
        foreach ($this->bladeFiles() as $file) {
            $relative = $this->relativePath($file);
            if ($relative === 'resources/views/components/validation-errors.blade.php') {
                continue;
            }
            $source = $this->stripBladeComments((string) file_get_contents($file));
            if (preg_match_all('/^\s*@if\s*\(\$errors->any\(\)/m', $source, $matches, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }
            if (isset(self::OWN_ERROR_MARKUP[$relative])) {
                $seen[$relative] = true;

                continue;
            }
            foreach ($matches[0] as [, $offset]) {
                $violations[] = sprintf('%s:%d', $relative, $this->lineOf($source, (int) $offset));
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "D1 Handgebauter Fehlerblock — `<x-validation-errors>` (bzw. `first`) verwenden:\n" . implode("\n", $violations));
        $this->assertSame([], array_values(array_diff(array_keys(self::OWN_ERROR_MARKUP), array_keys($seen))), 'Veraltete Ausnahmen in OWN_ERROR_MARKUP.');
    }

    public function test_screen_views_format_dates_with_the_macros(): void {
        /** @var list<string> $baseline */
        $baseline = require __DIR__ . '/baselines/date-format.php';
        $found = [];
        foreach ($this->bladeFiles() as $file) {
            $relative = $this->relativePath($file);
            if (preg_match(self::NOT_A_SCREEN, $relative) === 1) {
                continue;
            }
            if (preg_match('/->format\(\s*[\'"]d\.m\.Y/', $this->stripBladeComments((string) file_get_contents($file))) === 1) {
                $found[] = $relative;
            }
        }
        sort($found);

        $this->assertSame([], array_values(array_diff($found, $baseline)), "D2 Hart kodiertes Datumsformat — `->fdate()` bzw. `->fdatetime()` verwenden (Zeit ohne Umrechnung: `->translatedFormat(Formats::dateTime())`):\n" . implode("\n", array_diff($found, $baseline)));
        $this->assertSame([], array_values(array_diff($baseline, $found)), "Erledigt — aus baselines/date-format.php streichen:\n" . implode("\n", array_diff($baseline, $found)));
    }

    public function test_static_alerts_carry_a_role(): void {
        $violations = [];
        foreach ($this->bladeFiles() as $file) {
            $relative = $this->relativePath($file);
            if (preg_match('~^resources/views/(legacy|vendor|mail|emails|pdf|errors|components|layouts)/|/pdf/|/print/~', $relative) === 1) {
                continue;
            }
            $source = $this->stripBladeComments((string) file_get_contents($file));
            if (preg_match_all('/<(?:div|p|section|span)\b[^<>]*?class="alert(?: [^"]*)?"[^<>]*?>/', $source, $matches, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }
            foreach ($matches[0] as [$tag, $offset]) {
                // Dynamische Klassen oder Attribute entscheidet die Seite selbst.
                if (! str_contains($tag, 'role=') && preg_match('/\{\{|@|\$/', $tag) !== 1) {
                    $violations[] = sprintf('%s:%d', $relative, $this->lineOf($source, (int) $offset));
                }
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "D3 Hinweisblock ohne `role` — Fehler/Warnung `role=\"alert\"`, sonst `role=\"status\"`:\n" . implode("\n", $violations));
    }
}
