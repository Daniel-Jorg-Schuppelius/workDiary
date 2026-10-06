<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChartFrameTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\UI;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Rahmen der Diagramme (Konsolidierungs-Audit 2026-10, k4-12): Die elf
 * Diagrammkomponenten zeichnen Kopf, Hinweis, Leerzustand, Canvas, SVG-Hülle
 * und Datentabelle über <x-charts.frame>. Der Test hält das Markup des
 * Rahmens je Komponente fest; die Sparkline bleibt ohne Rahmen.
 */
class ChartFrameTest extends TestCase {
    private const STAND = '2026-10-01 08:15:00';

    private const FIGURE = '<figure class="wd-chart rounded-box border border-base-300 bg-base-100 p-3">';

    /**
     * Je Komponente: Attribute, gefüllte Daten, leere Daten, Symbol des
     * Leerzustands, Canvas für charts.js, Zeitraum im Kopf.
     *
     * @return array<string, array{0: string, 1: array<string, mixed>, 2: array<string, mixed>, 3: string, 4: bool, 5: bool}>
     */
    public static function charts(): array {
        $series = [['x' => 'Jan', 'y' => 5], ['x' => 'Feb', 'y' => 3]];
        $stack = [['x' => 'Jan', 'a' => 3], ['x' => 'Feb', 'a' => 5]];
        $bands = [['key' => 'a', 'label' => 'Band A']];
        $boxes = [
            ['x' => 'Jan', 'min' => 1, 'q1' => 2, 'median' => 3, 'q3' => 4, 'max' => 5],
            ['x' => 'Feb', 'min' => 2, 'q1' => 3, 'median' => 4, 'q3' => 5, 'max' => 6],
        ];
        $matrix = [['label' => 'Jan', 'cells' => [['value' => 3]]], ['label' => 'Feb', 'cells' => [['value' => 5]]]];

        return [
            'area-stack' => [':series="$s" :bands="$b"', ['s' => $stack, 'b' => $bands], ['s' => [], 'b' => $bands], 'area_chart', false, true],
            'bar' => [':series="$s"', ['s' => $series], ['s' => []], 'bar_chart', true, true],
            'bar-h' => [':series="$s"', ['s' => $series], ['s' => []], 'bar_chart', true, false],
            'boxplot' => [':series="$s"', ['s' => $boxes], ['s' => []], 'candlestick_chart', true, false],
            'bullet' => [':series="$s"', ['s' => $series], ['s' => []], 'bar_chart', true, false],
            'heatmap' => [':rows="$s" :col-labels="[1]"', ['s' => $matrix], ['s' => []], 'grid_on', false, false],
            'line' => [':series="$s"', ['s' => $series], ['s' => []], 'show_chart', true, true],
            'pareto' => [':series="$s"', ['s' => $series], ['s' => []], 'align_vertical_bottom', true, false],
            'scatter' => [':series="$s"', ['s' => $series], ['s' => []], 'scatter_plot', true, true],
            'stacked-bar' => [':series="$s" :bands="$b"', ['s' => $stack, 'b' => $bands], ['s' => [], 'b' => $bands], 'stacked_bar_chart', true, true],
            'waterfall' => [':series="$s"', ['s' => $series], ['s' => []], 'waterfall_chart', true, false],
        ];
    }

    #[DataProvider('charts')]
    public function test_chart_renders_frame_around_drawing_and_table(string $attributes, array $filled, array $empty, string $icon, bool $canvas, bool $range): void {
        $html = $this->squash($this->renderChart($this->dataName(), $attributes, $filled));

        $this->assertSame(1, substr_count($html, '<figure'));
        $this->assertStringStartsWith($this->frameHead(range: $range ? ' · Jan – Feb' : ''), $html);
        $this->assertStringEndsWith('</figure>', $html);
        $this->assertStringNotContainsString('wd-chart-empty', $html);

        // Canvas für charts.js nur mit Kontrakt; das SVG trägt dann die Klasse, die charts.js ausblendet.
        $this->assertSame($canvas ? 1 : 0, substr_count($html, 'data-wd-chart='));
        if ($this->dataName() === 'heatmap') {
            $this->assertStringNotContainsString('<svg', $html);
        } else {
            $this->assertMatchesRegularExpression(
                '~<svg viewBox="0 0 640 [\d.]+" role="img" aria-label="Titel &amp; Co" class="' . ($canvas ? 'wd-chart-svg ' : '') . 'mt-2 w-full">~',
                $html,
            );
        }

        // Gleichwertige Datentabelle nach der Zeichnung.
        $this->assertSame(1, substr_count($html, 'wd-chart-table'));
        $this->assertMatchesRegularExpression('~<div class="wd-chart-table mt-2 [^"]+">.*<table.*Jan.*Feb.*</table>~', $html);
        if ($canvas) {
            $this->assertLessThan(strpos($html, '<svg viewBox'), strpos($html, 'data-wd-chart='));
            $this->assertLessThan(strpos($html, 'wd-chart-table'), strpos($html, '</svg>'));
        }
    }

    #[DataProvider('charts')]
    public function test_chart_renders_empty_state_inside_frame(string $attributes, array $filled, array $empty, string $icon, bool $canvas, bool $range): void {
        $html = $this->squash($this->renderChart($this->dataName(), $attributes, $empty));

        $this->assertStringStartsWith($this->frameHead() . ' <div class="wd-chart-empty">', $html);
        $this->assertStringContainsString('<span class="material-symbols-outlined" aria-hidden="true">' . $icon . '</span>', $html);
        $this->assertStringContainsString('Noch keine Daten für dieses Diagramm.', $html);
        $this->assertStringNotContainsString('<svg', $html);
        $this->assertStringNotContainsString('<table', $html);
        $this->assertStringNotContainsString('wd-chart-table', $html);
        $this->assertStringNotContainsString('data-wd-chart=', $html);
    }

    #[DataProvider('charts')]
    public function test_chart_omits_as_of_and_note_when_not_given(string $attributes, array $filled, array $empty, string $icon, bool $canvas, bool $range): void {
        $html = $this->squash(Blade::render(
            '<x-charts.' . $this->dataName() . ' :title="$t" unit="h" ' . $attributes . ' />',
            $filled + ['t' => 'Titel & Co'],
        ));

        $this->assertStringStartsWith($this->frameHead(range: $range ? ' · Jan – Feb' : '', extras: false) . ' <', $html);
        $this->assertStringNotContainsString('</figcaption> <p', $html);
    }

    public function test_every_chart_component_draws_through_the_frame(): void {
        $files = glob(resource_path('views/components/charts/*.blade.php')) ?: [];
        $names = array_map(fn(string $file): string => basename($file, '.blade.php'), $files);
        // Teilstücke (_…), der Rahmen selbst und die rahmenlose Sparkline sind keine Diagramme mit Rahmen.
        $charts = array_values(array_filter($names, fn(string $name): bool => ! str_starts_with($name, '_') && ! in_array($name, ['frame', 'sparkline'], true)));

        // Ein neues Diagramm gehört in charts() — sonst bleibt sein Rahmen ungeprüft.
        $this->assertEqualsCanonicalizing(array_keys(self::charts()), $charts);

        foreach ($charts as $name) {
            $source = (string) file_get_contents(resource_path("views/components/charts/{$name}.blade.php"));
            $this->assertSame(1, substr_count($source, '<x-charts.frame '), "{$name} nutzt <x-charts.frame> nicht.");
            foreach (['<figure', '<figcaption', 'wd-chart-empty', 'components.charts._canvas', 'isoFormat('] as $own) {
                $this->assertStringNotContainsString($own, $source, "{$name} baut den Rahmen selbst ({$own}).");
            }
        }
    }

    public function test_frame_places_legend_between_drawing_and_table(): void {
        $html = $this->squash(Blade::render(<<<'BLADE'
            <x-charts.frame title="Titel" unit="h" :view-box="[640, 100]">
                <circle r="1" />
                <x-slot:legend><p>Legende</p></x-slot:legend>
                <x-slot:head><tr><th>Kopf</th></tr></x-slot:head>
                <x-slot:rows><tr><td>Zeile</td></tr></x-slot:rows>
            </x-charts.frame>
            BLADE));

        $this->assertStringContainsString(
            '<svg viewBox="0 0 640 100" role="img" aria-label="Titel" class="mt-2 w-full"> <circle r="1" /> </svg> <p>Legende</p> <div class="wd-chart-table mt-2 max-h-48 overflow-y-auto">',
            $html,
        );
        $this->assertMatchesRegularExpression('~<thead>\s*<tr><th>Kopf</th></tr>\s*</thead>.*<tbody>\s*<tr><td>Zeile</td></tr>\s*</tbody>~', $html);
        $this->assertStringNotContainsString('data-wd-chart=', $html);
    }

    public function test_frame_without_view_box_and_table_renders_the_slot_alone(): void {
        $html = $this->squash(Blade::render('<x-charts.frame title="Titel" unit="h"><div class="matrix">Matrix</div></x-charts.frame>'));

        $this->assertStringContainsString('</figcaption> <div class="matrix">Matrix</div> </figure>', $html);
        $this->assertStringNotContainsString('<svg', $html);
        $this->assertStringNotContainsString('<table', $html);
    }

    public function test_sparkline_stays_without_frame(): void {
        $html = Blade::render('<x-charts.sparkline :values="[1, 2, 3]" unit="h" />');

        $this->assertStringContainsString('<svg viewBox="0 0 120 24"', $html);
        foreach (['<figure', '<figcaption', 'wd-chart', '<table', 'Stand:'] as $frame) {
            $this->assertStringNotContainsString($frame, $html);
        }
    }

    /** @param array<string, mixed> $data */
    private function renderChart(string $name, string $attributes, array $data): string {
        return Blade::render(
            '<x-charts.' . $name . ' :title="$t" unit="h" :computed-at="$at" :note="$n" ' . $attributes . ' />',
            $data + ['t' => 'Titel & Co', 'at' => self::STAND, 'n' => 'Datenbasis: Test'],
        );
    }

    /** Kopf des Rahmens, Leerraum zusammengefasst; $extras = Datenstand und Hinweiszeile. */
    private function frameHead(string $range = '', bool $extras = true): string {
        return self::FIGURE
            . ' <figcaption> <span class="font-[\'Space_Grotesk\'] text-sm font-semibold">Titel &amp; Co</span>'
            . ' <span class="ml-2 text-xs text-muted"> h' . $range
            . ($extras ? ' · Stand: ' . Carbon::parse(self::STAND)->isoFormat('L LT') : '') . ' </span>'
            . ' </figcaption>' . ($extras ? ' <p class="mt-1 text-xs text-muted">Datenbasis: Test</p>' : '');
    }

    private function squash(string $html): string {
        return trim((string) preg_replace('/\s+/u', ' ', $html));
    }
}
