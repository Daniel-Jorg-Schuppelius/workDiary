<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ActionMenuComponentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\UI;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * <x-action-menu> (MVP-967): <details>-Menü mit denselben Bausteinen wie der
 * Aktionsslot. Schließen, Einzelaktion und Abschnitt im ⋯-Menü regelt
 * resources/js/action-menu.js bzw. toolbar-overflow.js.
 */
class ActionMenuComponentTest extends TestCase {
    public function test_menu_renders_label_icon_and_items(): void {
        $html = Blade::render(<<<'BLADE'
            <x-action-menu icon="download" label="Export">
                <x-icon-btn icon="receipt" href="/x" show-label>XRechnung</x-icon-btn>
                <x-action-form action="/y"><x-button type="submit" tone="ghost">Neu berechnen</x-button></x-action-form>
            </x-action-menu>
            BLADE);

        $this->assertMatchesRegularExpression('/<details class="dropdown dropdown-end"\s+data-menu data-action-menu/', $html);
        $this->assertStringContainsString('<span>Export</span>', $html);
        $this->assertStringContainsString('data-icon="expand_more"', $html);
        $this->assertMatchesRegularExpression('~data-action-menu-items>.*href="/x".*<form method="POST" action="/y".*name="_token"~s', $html);
    }

    public function test_icon_only_menu_keeps_an_accessible_name(): void {
        $html = Blade::render('<x-action-menu icon="swap_horiz" label="Status ändern" icon-only size="xs" tone="outline"><x-button>A</x-button></x-action-menu>');

        $this->assertStringContainsString('title="Status ändern" aria-label="Status ändern"', $html);
        $this->assertStringNotContainsString('<span>Status ändern</span>', $html);
        $this->assertStringContainsString('btn-xs btn-outline', $html);
    }

    public function test_placement_and_alignment(): void {
        $html = Blade::render('<x-action-menu label="Neu" placement="bar" align="start"><x-button>A</x-button></x-action-menu>');

        $this->assertStringContainsString('data-toolbar-placement="bar"', $html);
        $this->assertStringNotContainsString('dropdown-end', $html);
    }
}
