<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DetailGridComponentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\UI;

use Dom\{Element, HTMLDocument};
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Bauformen von `<x-detail-grid>` (Konsolidierungs-Audit 2026-10, k4-13):
 * `list` (Label-Spalte, Wert daneben) bleibt wie bisher, `cells` (Label über
 * Wert) und `split` (Wert am rechten Rand) tragen das Raster, das vorher als
 * `<dl><div><dt>…` von Hand stand.
 */
final class DetailGridComponentTest extends TestCase {
    private function root(string $blade): Element {
        $root = HTMLDocument::createFromString('<!DOCTYPE html><body>' . Blade::render($blade) . '</body>', LIBXML_NOERROR)->body->firstElementChild;
        $this->assertNotNull($root);

        return $root;
    }

    /** @return list<string> */
    private function classes(?Element $element): array {
        $classes = preg_split('/\s+/', trim((string) $element?->getAttribute('class')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        sort($classes);

        return $classes;
    }

    /** @return list<string> */
    private function childNames(Element $element): array {
        $names = [];
        foreach ($element->children as $child) {
            $names[] = $child->localName;
        }

        return $names;
    }

    public function test_list_layout_renders_as_before(): void {
        $dl = $this->root('<x-detail-grid class="mt-4"><x-detail-grid.row label="A" class="font-mono" data-x="1">1</x-detail-grid.row><x-detail-grid.row label="B" value="2" /></x-detail-grid>');

        $this->assertSame('dl', $dl->localName);
        $this->assertSame(['gap-x-4', 'gap-y-1', 'grid', 'grid-cols-[max-content_1fr]', 'mt-4', 'text-sm'], $this->classes($dl));
        $this->assertSame(['dt', 'dd', 'dt', 'dd'], $this->childNames($dl));
        $this->assertSame(['text-muted'], $this->classes($dl->children[0]));
        $this->assertSame(['font-mono'], $this->classes($dl->children[1]));
        $this->assertSame('1', $dl->children[1]->getAttribute('data-x'));
        $this->assertSame(['A', '1', 'B', '2'], array_map(static fn (Element $e): string => trim($e->textContent), iterator_to_array($dl->children)));
    }

    public function test_list_layout_drops_a_row_without_value(): void {
        $dl = $this->root('<x-detail-grid><x-detail-grid.row label="A" :value="null" /><x-detail-grid.row label="B" value="" /><x-detail-grid.row label="C">   </x-detail-grid.row><x-detail-grid.row label="D" :value="0" /></x-detail-grid>');

        $this->assertSame(['dt', 'dd'], $this->childNames($dl));
        $this->assertSame('D', trim($dl->children[0]->textContent));
    }

    public function test_cells_layout_sets_the_columns(): void {
        $columns = fn (string $attributes): array => array_values(array_filter(
            $this->classes($this->root('<x-detail-grid layout="cells" ' . $attributes . '></x-detail-grid>')),
            static fn (string $class): bool => str_contains($class, 'grid-cols-'),
        ));

        $this->assertSame(['gap-x-6', 'gap-y-2', 'grid', 'grid-cols-1', 'md:grid-cols-2', 'text-sm'], $this->classes($this->root('<x-detail-grid layout="cells"></x-detail-grid>')));
        $this->assertSame(['grid-cols-1'], $columns(':cols="1"'));
        $this->assertSame(['grid-cols-1', 'md:grid-cols-2'], $columns(':cols="2"'));
        $this->assertSame(['grid-cols-1', 'md:grid-cols-3'], $columns('cols="3"'));
        $this->assertSame(['grid-cols-1', 'md:grid-cols-4', 'sm:grid-cols-2'], $columns(':cols="4"'));
        $this->assertSame(['grid-cols-1', 'md:grid-cols-2', 'mt-4'], array_values(array_filter(
            $this->classes($this->root('<x-detail-grid layout="cells" class="mt-4"></x-detail-grid>')),
            static fn (string $class): bool => str_contains($class, 'grid-cols-') || $class === 'mt-4',
        )));
    }

    public function test_cells_layout_stacks_label_over_value(): void {
        $dl = $this->root('<x-detail-grid layout="cells" :cols="3"><x-detail-grid.row label="A" class="tabular-nums">1</x-detail-grid.row><x-detail-grid.row label="B" full>2</x-detail-grid.row></x-detail-grid>');

        $this->assertSame(['div', 'div'], $this->childNames($dl));
        $cell = $dl->children[0];
        $this->assertSame(['min-w-0'], $this->classes($cell));
        $this->assertSame(['dt', 'dd'], $this->childNames($cell));
        $this->assertSame(['text-muted'], $this->classes($cell->children[0]));
        $this->assertSame(['tabular-nums'], $this->classes($cell->children[1]));
        $this->assertSame(['col-span-full', 'min-w-0'], $this->classes($dl->children[1]));
        // Steuerattribute bleiben nicht am Element hängen.
        $this->assertFalse($dl->hasAttribute('layout') || $dl->hasAttribute('cols') || $dl->children[1]->children[1]->hasAttribute('full'));
    }

    public function test_small_labels_shrink_the_label_only(): void {
        $dl = $this->root('<x-detail-grid layout="cells" small-labels><x-detail-grid.row label="A">1</x-detail-grid.row></x-detail-grid>');

        $this->assertSame(['text-muted', 'text-xs'], $this->classes($dl->querySelector('dt')));
        $this->assertSame([], $this->classes($dl->querySelector('dd')));
        $this->assertFalse($dl->hasAttribute('small-labels'));
    }

    /** Eine Zelle ohne Wert bleibt stehen, sonst rutschen die folgenden Zellen. */
    public function test_cells_keep_an_empty_cell_with_a_dash(): void {
        $dl = $this->root('<x-detail-grid layout="cells"><x-detail-grid.row label="A" :value="null" /><x-detail-grid.row label="B" value="" /><x-detail-grid.row label="C">  </x-detail-grid.row><x-detail-grid.row label="D" value="4" /></x-detail-grid>');

        $this->assertSame(['div', 'div', 'div', 'div'], $this->childNames($dl));
        $this->assertSame(['—', '—', '—', '4'], array_map(static fn (Element $cell): string => trim($cell->children[1]->textContent), iterator_to_array($dl->children)));
    }

    public function test_split_layout_puts_the_value_at_the_right_edge(): void {
        $dl = $this->root('<x-detail-grid layout="split"><x-detail-grid.row label="A" class="font-mono">1</x-detail-grid.row><x-detail-grid.row label="B" :value="null" /></x-detail-grid>');

        $this->assertSame(['gap-x-8', 'gap-y-1', 'grid', 'grid-cols-1', 'text-sm'], $this->classes($dl));
        $this->assertSame(['flex', 'gap-4', 'items-baseline', 'justify-between'], $this->classes($dl->children[0]));
        $this->assertSame(['text-muted'], $this->classes($dl->children[0]->children[0]));
        $this->assertSame(['font-mono'], $this->classes($dl->children[0]->children[1]));
        $this->assertSame('—', trim($dl->children[1]->children[1]->textContent));

        $wide = $this->root('<x-detail-grid layout="split" :cols="2"><x-detail-grid.row label="A" full>1</x-detail-grid.row></x-detail-grid>');
        $this->assertContains('md:grid-cols-2', $this->classes($wide));
        $this->assertContains('col-span-full', $this->classes($wide->children[0]));
    }

    public function test_divided_draws_a_line_between_split_rows(): void {
        $dl = $this->root('<x-detail-grid layout="split" divided><x-detail-grid.row label="A">1</x-detail-grid.row><x-detail-grid.row label="B">2</x-detail-grid.row></x-detail-grid>');

        foreach ($dl->children as $row) {
            $this->assertSame([], array_diff(['border-b', 'border-base-200/70', 'pb-1', 'last:border-0', 'last:pb-0'], $this->classes($row)));
        }
        $this->assertFalse($dl->hasAttribute('divided'));
        $this->assertNotContains('border-b', $this->classes($this->root('<x-detail-grid layout="split"><x-detail-grid.row label="A">1</x-detail-grid.row></x-detail-grid>')->children[0]));
    }

    public function test_a_row_may_override_the_layout_of_its_grid(): void {
        $dl = $this->root('<x-detail-grid layout="split"><x-detail-grid.row label="A">1</x-detail-grid.row><x-detail-grid.row label="B" layout="cells">Text</x-detail-grid.row></x-detail-grid>');

        $this->assertContains('justify-between', $this->classes($dl->children[0]));
        $this->assertSame(['min-w-0'], $this->classes($dl->children[1]));
        $this->assertFalse($dl->children[1]->children[1]->hasAttribute('layout'));
    }

    public function test_a_list_inside_a_cell_names_its_layout(): void {
        $dl = $this->root('<x-detail-grid layout="cells"><x-detail-grid.row label="A"><x-detail-grid layout="list"><x-detail-grid.row label="B">2</x-detail-grid.row></x-detail-grid></x-detail-grid.row></x-detail-grid>');

        $inner = $dl->querySelector('dd > dl');
        $this->assertNotNull($inner);
        $this->assertContains('grid-cols-[max-content_1fr]', $this->classes($inner));
        $this->assertSame(['dt', 'dd'], $this->childNames($inner));
    }
}
