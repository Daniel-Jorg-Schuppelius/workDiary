<?php
/*
 * Created on   : Sun Nov 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IconComponentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\UI;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * `<x-icon>` schreibt sein Markup ohne View (Phase 188) — Zeichen für Zeichen wie die frühere Blade-Datei.
 */
class IconComponentTest extends TestCase {
    public function test_plain_icon_renders_the_material_symbol_span(): void {
        $this->assertSame(
            "[<span\n    class=\"material-symbols-outlined leading-none align-middle shrink-0 select-none\"\n    style=\"font-variation-settings: 'FILL' 0, 'wght' 400;\"\n    aria-hidden=\"true\"\n    data-icon=\"edit\"\n>edit</span>\n]",
            Blade::render('[<x-icon name="edit" />]'),
        );
    }

    public function test_props_escape_and_extra_attributes_merge_into_the_span(): void {
        $html = Blade::render('<x-icon :name="$name" :filled="true" weight="600" size="2rem" class="text-error" data-x="1" />', ['name' => 'a&b']);

        $this->assertStringContainsString('class="material-symbols-outlined leading-none align-middle shrink-0 select-none text-error" data-x="1"', $html);
        $this->assertStringContainsString("style=\"font-variation-settings: 'FILL' 1, 'wght' 600; font-size: 2rem;\"", $html);
        $this->assertStringContainsString('data-icon="a&amp;b"', $html);
        $this->assertStringContainsString('>a&amp;b</span>', $html);
        $this->assertStringNotContainsString('name=', $html);
    }

    public function test_icon_inside_other_components_keeps_working(): void {
        $html = Blade::render('<x-icon-btn icon="edit" label="Bearbeiten" href="/x" />');

        $this->assertStringContainsString('data-icon="edit"', $html);
        $this->assertStringContainsString('aria-label="Bearbeiten"', $html);
    }
}
