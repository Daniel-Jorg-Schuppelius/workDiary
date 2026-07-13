<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GlossaryTermTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Help;

use Illuminate\Support\Facades\{Blade, Lang};
use Tests\TestCase;

/**
 * Begriffs-Tooltips (Feature 039, B3/MVP-344): die x-term-Komponente
 * löst Glossar-Keys aus lang/<locale>/glossary.php als daisyUI-Tooltip
 * auf; unbekannte Keys rendern den Inhalt unverändert (nie ein roher
 * „glossary.x"-Key als Tooltip). Die drei B3-Begriffe existieren in
 * allen fünf Sprachen.
 */
class GlossaryTermTest extends TestCase {
    private const NEW_KEYS = ['abnahme', 'prozedur', 'zeitkonto'];

    public function test_x_term_renders_tooltip_for_new_glossary_keys(): void {
        foreach (self::NEW_KEYS as $key) {
            $html = Blade::render('<x-term glossary="' . $key . '">Begriff</x-term>');

            $this->assertStringContainsString('data-tip', $html, "x-term ohne Tooltip für '$key'");
            $this->assertStringContainsString(e(__('glossary.' . $key)), $html, "Tooltip-Text fehlt für '$key'");
            $this->assertStringContainsString('Begriff', $html);
        }
    }

    public function test_x_term_renders_plain_content_for_unknown_key(): void {
        $html = Blade::render('<x-term glossary="gibt_es_nicht">Begriff</x-term>');

        $this->assertStringNotContainsString('data-tip', $html);
        $this->assertStringNotContainsString('glossary.gibt_es_nicht', $html);
        $this->assertStringContainsString('Begriff', $html);
    }

    public function test_new_glossary_keys_exist_in_all_five_locales(): void {
        foreach (['de', 'en', 'fr', 'it', 'es'] as $locale) {
            foreach (self::NEW_KEYS as $key) {
                $this->assertTrue(
                    Lang::has('glossary.' . $key, $locale),
                    "glossary.$key fehlt in Locale $locale",
                );
            }
        }
    }
}
