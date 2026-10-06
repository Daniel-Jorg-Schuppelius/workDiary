<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ButtonBadgeComponentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\UI;

use Dom\{Element, HTMLDocument};
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Optionen, mit denen `<x-status-badge>`, `<x-button>` und `<x-icon-btn>` das
 * bisher handgeschriebene Markup wertgleich tragen (Konsolidierungs-Audit
 * 2026-10, k4-13): Ton `plain` ohne Tonklasse und `icon-size` für Icons, die
 * nicht in der Standardgröße stehen.
 */
final class ButtonBadgeComponentTest extends TestCase {
    private function root(string $blade): Element {
        $root = HTMLDocument::createFromString('<!DOCTYPE html><body>' . Blade::render($blade) . '</body>', LIBXML_NOERROR)->body->firstElementChild;
        $this->assertNotNull($root);

        return $root;
    }

    /** @return list<string> */
    private function classes(Element $element): array {
        $classes = preg_split('/\s+/', trim((string) $element->getAttribute('class')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        sort($classes);

        return $classes;
    }

    public function test_badge_plain_tone_renders_no_tone_class(): void {
        $this->assertSame(['badge', 'badge-sm'], $this->classes($this->root('<x-status-badge tone="plain">A</x-status-badge>')));
        $this->assertSame(['badge', 'badge-outline', 'badge-xs'], $this->classes($this->root('<x-status-badge tone="plain" size="xs" outline>A</x-status-badge>')));
    }

    public function test_badge_defaults_and_unknown_tone_stay_ghost(): void {
        $this->assertSame(['badge', 'badge-ghost', 'badge-sm'], $this->classes($this->root('<x-status-badge>A</x-status-badge>')));
        $this->assertSame(['badge', 'badge-ghost', 'badge-sm'], $this->classes($this->root('<x-status-badge tone="gibt-es-nicht">A</x-status-badge>')));
        $this->assertSame(['badge', 'badge-sm', 'badge-success'], $this->classes($this->root('<x-status-badge tone="success">A</x-status-badge>')));
    }

    public function test_button_plain_tone_renders_no_tone_class(): void {
        $this->assertSame(['btn', 'btn-sm', 'gap-1'], $this->classes($this->root('<x-button tone="plain">A</x-button>')));
        $this->assertSame(['btn', 'btn-xs', 'gap-1'], $this->classes($this->root('<x-button tone="plain" size="xs" :href="\'/x\'">A</x-button>')));
        $this->assertSame(['btn', 'btn-xs'], $this->classes($this->root('<x-icon-btn tone="plain" icon="sync" label="A" />')));
    }

    public function test_button_defaults_and_unknown_tone_are_unchanged(): void {
        $this->assertSame(['btn', 'btn-primary', 'btn-sm', 'gap-1'], $this->classes($this->root('<x-button>A</x-button>')));
        $this->assertSame(['btn', 'btn-primary', 'btn-sm', 'gap-1'], $this->classes($this->root('<x-button tone="gibt-es-nicht">A</x-button>')));
        $this->assertSame(['btn', 'btn-ghost', 'btn-xs'], $this->classes($this->root('<x-icon-btn icon="sync" label="A" />')));
        $this->assertSame(['btn', 'btn-ghost', 'btn-xs'], $this->classes($this->root('<x-icon-btn tone="gibt-es-nicht" icon="sync" label="A" />')));
    }

    public function test_icon_size_sets_the_font_size_of_the_icon_only_when_given(): void {
        $icon = fn (string $blade): string => (string) $this->root($blade)->querySelector('[data-icon]')?->getAttribute('style');

        $this->assertStringContainsString('font-size: 1.1rem;', $icon('<x-button icon="link" icon-size="1.1rem">A</x-button>'));
        $this->assertStringContainsString('font-size: 1rem;', $icon('<x-button icon-trailing="link" icon-size="1rem">A</x-button>'));
        $this->assertStringContainsString('font-size: 1.1rem;', $icon('<x-icon-btn icon="delete" icon-size="1.1rem" label="A" />'));
        $this->assertStringNotContainsString('font-size', $icon('<x-button icon="link">A</x-button>'));
        $this->assertStringNotContainsString('font-size', $icon('<x-icon-btn icon="delete" label="A" />'));
    }

    /** Ein roher Knopf ohne `type` sendet sein Formular ab — die Komponente nur mit `type="submit"`. */
    public function test_button_type_defaults_to_button_and_passes_submit_through(): void {
        $this->assertSame('button', $this->root('<x-button>A</x-button>')->getAttribute('type'));
        $this->assertSame('submit', $this->root('<x-button type="submit" name="action" value="import" formnovalidate>A</x-button>')->getAttribute('type'));
        $submit = $this->root('<x-button type="submit" name="action" value="import" form="f1" formaction="/x">A</x-button>');
        $this->assertSame(['import', 'action', 'f1', '/x'], [$submit->getAttribute('value'), $submit->getAttribute('name'), $submit->getAttribute('form'), $submit->getAttribute('formaction')]);
    }
}
