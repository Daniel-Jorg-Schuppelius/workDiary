<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PageToolbarComponentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\UI;

use App\Http\Middleware\RememberListUrl;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Seitenkopf mit Überlaufmenü (MVP-966): Das Markup trägt die Anker für
 * resources/js/toolbar-overflow.js, die Platzierung der Aktionen und den
 * Rückpfeil samt gemerkter Listenfilter. Die Verteilung selbst prüfen
 * tests/frontend/toolbar-overflow.test.mjs und tests/e2e/toolbar-overflow.spec.ts.
 */
class PageToolbarComponentTest extends TestCase {
    public function test_actions_get_the_overflow_anchors_and_a_hidden_more_menu(): void {
        $html = Blade::render('<x-page-toolbar subtitle="Kopf"><x-slot:actions><x-button>Neu</x-button></x-slot:actions></x-page-toolbar>');

        $this->assertStringContainsString('data-toolbar>', $html);
        $this->assertStringContainsString('data-toolbar-head', $html);
        $this->assertStringContainsString('data-toolbar-actions', $html);
        $this->assertMatchesRegularExpression('/<details[^>]*data-toolbar-more[^>]*data-menu[^>]*hidden/', $html);
        $this->assertStringContainsString('aria-label="Weitere Aktionen"', $html);
        $this->assertStringContainsString('data-toolbar-menu-main></div>', $html);
    }

    public function test_toolbar_without_actions_renders_no_menu(): void {
        $html = Blade::render('<x-page-toolbar subtitle="Nur Text" />');

        $this->assertStringNotContainsString('data-toolbar-actions', $html);
        $this->assertStringNotContainsString('data-toolbar-more', $html);
    }

    public function test_toolbar_without_head_keeps_actions_right_aligned(): void {
        $html = Blade::render('<x-page-toolbar><x-slot:actions><x-button>Neu</x-button></x-slot:actions></x-page-toolbar>');

        $this->assertStringNotContainsString('data-toolbar-head', $html);
        $this->assertMatchesRegularExpression('/class="ms-auto[^"]*"\s+data-toolbar-actions/', $html);
    }

    public function test_buttons_carry_only_known_placements(): void {
        $html = Blade::render(<<<'BLADE'
            <x-icon-btn icon="send" placement="bar" show-label>Stellen</x-icon-btn>
            <x-button icon="delete" tone="error" placement="danger">Löschen</x-button>
            <x-icon-btn icon="percent" :href="'/k'" placement="menu" label="Konditionen" />
            <x-icon-btn icon="edit" placement="irgendwo" label="Bearbeiten" />
            <x-button>Ohne</x-button>
            BLADE);

        $this->assertSame(3, substr_count($html, 'data-toolbar-placement='));
        $this->assertStringContainsString('data-toolbar-placement="bar"', $html);
        $this->assertStringContainsString('data-toolbar-placement="danger"', $html);
        $this->assertStringContainsString('data-toolbar-placement="menu"', $html);
    }

    public function test_back_route_returns_to_the_remembered_list_filters(): void {
        session()->put(RememberListUrl::SESSION_KEY, [
            'invoices.index' => route('invoices.index') . '?status=draft&page=2',
        ]);

        $html = Blade::render('<x-page-toolbar title="R1" back-route="invoices.index" back-label="Zur Liste" />');

        $this->assertStringContainsString('href="' . e(route('invoices.index') . '?status=draft&page=2') . '"', $html);
        $this->assertStringContainsString('aria-label="Zur Liste"', $html);
        $this->assertStringContainsString('data-icon="arrow_back"', $html);
    }

    public function test_back_route_without_memory_leads_to_the_plain_list(): void {
        $html = Blade::render('<x-page-toolbar title="R1" back-route="invoices.index" />');

        $this->assertStringContainsString('href="' . route('invoices.index') . '"', $html);
        $this->assertStringContainsString('aria-label="Zurück"', $html);
    }

    public function test_back_url_wins_and_badges_sit_next_to_the_title(): void {
        $html = Blade::render(<<<'BLADE'
            <x-page-toolbar title="R1" back="/eltern/1" back-route="invoices.index">
                <x-slot:badges><span class="badge">Mahnsperre</span></x-slot:badges>
                <x-slot:actions><x-button>PDF</x-button></x-slot:actions>
            </x-page-toolbar>
            BLADE);

        $this->assertStringContainsString('href="/eltern/1"', $html);
        $head = substr($html, 0, (int) strpos($html, 'data-toolbar-actions'));
        $this->assertStringContainsString('Mahnsperre', $head);
    }
}
