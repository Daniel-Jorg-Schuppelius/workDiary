<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiCapabilityLabelCoverageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Ai;

use Tests\TestCase;

/** Jede KI-Fähigkeit hat eine lesbare Bezeichnung in allen Sprachen — sonst zeigt die Verwaltung den Rohschlüssel (Vollscan 2026-10-21). */
final class AiCapabilityLabelCoverageTest extends TestCase {
    public function test_every_capability_has_a_label_in_every_locale(): void {
        $missing = [];
        foreach (array_keys((array) config('ai.capabilities')) as $key) {
            foreach (['de', 'en', 'fr', 'es', 'it'] as $locale) {
                if (! trans()->has('ai.capability_label.' . $key, $locale, false)) {
                    $missing[] = $locale . ': ' . $key;
                }
            }
        }

        $this->assertSame([], $missing, 'KI-Fähigkeiten ohne Bezeichnung (lang/*/ai.php → capability_label).');
    }
}
