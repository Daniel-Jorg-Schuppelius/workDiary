<?php
/*
 * Created on   : Mon Aug 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FilterBarComponentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\UI;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Kontrakt-Gate der Filterleiste: jedes Feld trägt ein Label (im Zweifel
 * sr-only), Eingabefelder eines auch sichtbar, und Schalter sortieren sich per
 * order-Utility hinter die Felder — der Standard-Filtersatz kommt als @include
 * vor den seitenspezifischen Feldern, sein Schalter landete sonst mittendrin.
 */
class FilterBarComponentTest extends TestCase {
    public function test_field_without_visible_label_stays_labelled_for_screenreaders(): void {
        $html = Blade::render('<x-filter-field label="Kunde" for="f-customer"><select id="f-customer"></select></x-filter-field>');

        $this->assertStringContainsString('for="f-customer"', $html);
        $this->assertStringContainsString('sr-only', $html);
    }

    public function test_inline_field_renders_visible_label_next_to_the_input(): void {
        $html = Blade::render('<x-filter-field label="Mindest-Aufwand (Minuten)" for="f-min" inline><input id="f-min" type="number" /></x-filter-field>');

        $this->assertStringContainsString('Mindest-Aufwand (Minuten)', $html);
        $this->assertStringNotContainsString('sr-only', $html);
    }

    public function test_toggle_renders_label_and_sorts_behind_the_fields(): void {
        $html = Blade::render('<x-filter-toggle name="hide_zero" id="f-hide" label="Ohne Werte ausblenden" :checked="true" data-autosubmit />');

        $this->assertStringContainsString('Ohne Werte ausblenden', $html);
        $this->assertStringContainsString('order-40', $html);
        $this->assertStringContainsString('data-autosubmit', $html);
        $this->assertStringContainsString('checked', $html);
        $this->assertStringContainsString('toggle-primary', $html);
    }

    public function test_toggle_tone_replaces_the_default_colour(): void {
        $html = Blade::render('<x-filter-toggle name="risk" label="Nur Risiko" tone="error" />');

        $this->assertStringContainsString('toggle-error', $html);
        $this->assertStringNotContainsString('toggle-primary', $html);
    }

    public function test_actions_sort_behind_the_toggles_and_bar_wraps(): void {
        $html = Blade::render('<x-filter-bar action="/x" reset="/x"><x-filter-toggle name="a" label="A" /></x-filter-bar>');

        $this->assertStringContainsString('order-50', $html);
        // Ohne Umbruch scrollen Filtern/Zurücksetzen unbemerkt aus dem Bild.
        $this->assertStringContainsString('flex-wrap', $html);
        $this->assertStringNotContainsString('overflow-x-auto', $html);
    }
}
