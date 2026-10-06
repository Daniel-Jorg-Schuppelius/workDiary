<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormSubmitterRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „Ein Formular hat einen Absender“ (Konsolidierungs-Audit
 * 2026-10, k4-13): `<x-button>` und `<x-icon-btn>` rendern ohne Angabe
 * `type="button"`. Steht so ein Knopf als einziger in einem Formular, sendet
 * er nichts — drei Stellen liefen so ins Leere (Stoppuhr „Stoppen“,
 * Projektaufgabe „Del“, Schließen-Kreuz der Tastenkürzel).
 *
 * Regel: ein `<form>`, das einen Komponenten-Knopf ohne `type` trägt, hat
 * einen erkennbaren Absender (`type="submit"`, roher `<button>` ohne `type`,
 * Autosubmit, ein Skript-Auslöser oder eine Komponente, die den Absender
 * selbst rendert).
 */
class FormSubmitterRuleTest extends TestCase {
    use ScansSourceTree;

    /** Was als Absender gilt — Typangabe, Autosubmit, Skript oder eine Komponente mit eigenem Absendeknopf. */
    private const SUBMITTER = '~type=["\']submit["\']|:type=|<button(?![^>]*\btype=)|<input[^>]*type="(?:submit|image)"|data-autosubmit|x-on:change|@change|requestSubmit|\.submit\(|x-on:submit|@submit|data-[a-z-]*submit|<x-modal|<x-action-form|<x-filter-bar~';

    private const TYPELESS_BUTTON = '~<x-(?:button|icon-btn)\b(?![^>]*\btype=)(?![^>]*\bhref)(?![^>]*:href)~s';

    public function test_forms_with_component_buttons_have_a_submitter(): void {
        $violations = [];
        foreach ($this->bladeFiles() as $file) {
            $relative = $this->relativePath($file);
            if (str_contains($relative, '/legacy/') || str_contains($relative, '/vendor/')) {
                continue;
            }
            $source = $this->stripBladeComments((string) file_get_contents($file));
            if (preg_match_all('~<form\b[^>]*>.*?</form>~s', $source, $forms, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }
            foreach ($forms[0] as [$form, $offset]) {
                if (preg_match(self::SUBMITTER, $form) === 1 || preg_match(self::TYPELESS_BUTTON, $form) !== 1) {
                    continue;
                }
                $violations[] = sprintf('%s:%d', $relative, $this->lineOf($source, (int) $offset));
            }
        }

        $this->assertSame([], $violations, "Formular ohne Absender — der Komponenten-Knopf braucht type=\"submit\":\n" . implode("\n", $violations));
    }
}
