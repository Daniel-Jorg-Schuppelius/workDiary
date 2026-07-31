<?php
/*
 * Created on   : Fri Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChartComponentsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\UI;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Kontrakt-Gate der neuen Chart-Komponenten (Feature 002, §Diagramm-UX aus
 * Spec 064): Leerzustand statt Null-Achse, gleichwertige Tabelle bzw.
 * Matrix-Werte sichtbar, fokussierbare Datenpunkte mit aria-label und
 * Drilldown-Link, Theming über Klassen statt Hex-Farben am Bildschirm.
 */
class ChartComponentsTest extends TestCase {
    use RefreshDatabase;

    // ---- <x-charts.bar-h> ----------------------------------------------

    public function test_bar_h_renders_empty_state_without_data(): void {
        $html = Blade::render('<x-charts.bar-h :title="$t" unit="h" :series="[]" />', ['t' => 'Top-N']);

        $this->assertStringContainsString('Noch keine Daten', $html);
        $this->assertStringNotContainsString('<svg viewBox', $html);
    }

    public function test_bar_h_renders_full_labels_values_and_drilldown(): void {
        $html = Blade::render('<x-charts.bar-h :title="$t" unit="h" :series="$s" y2-label="Soll" />', [
            't' => 'Top-Projekte',
            's' => [
                ['x' => 'Ein sehr langer Projektname mit Kunde', 'y' => 12.5, 'y2' => 10, 'url' => '/reports/project-details?project=abc'],
                ['x' => 'Zweites Projekt', 'y' => 3],
            ],
        ]);

        // Volles Label (x-charts.bar würde auf 10 Zeichen kürzen) + Wert + Link + aria.
        $this->assertStringContainsString('Ein sehr langer Projektname mi', $html);
        $this->assertStringContainsString('href="/reports/project-details?project=abc"', $html);
        $this->assertStringContainsString('aria-label="Ein sehr langer Projektname mit Kunde: 12.5 h, Soll: 10"', $html);
        $this->assertStringContainsString('tabindex="0"', $html);
        // Gleichwertige Tabelle unterhalb des Diagramms.
        $this->assertStringContainsString('Zweites Projekt', $html);
        $this->assertStringContainsString('fill-primary', $html);
        $this->assertDoesNotMatchRegularExpression('/(?:fill|stroke|background)[^"]*#[0-9a-f]{3,6}/i', $html);
    }

    // ---- <x-charts.stacked-bar> ----------------------------------------

    public function test_stacked_bar_renders_legend_segments_and_totals(): void {
        $html = Blade::render('<x-charts.stacked-bar :title="$t" unit="h" :series="$s" :bands="$b" />', [
            't' => 'Stunden nach Art',
            's' => [
                ['x' => 'KW 1', 'work' => 30, 'travel' => 5],
                ['x' => 'KW 2', 'work' => 20, 'travel' => 0],
            ],
            'b' => [
                ['key' => 'work', 'label' => 'Arbeit'],
                ['key' => 'travel', 'label' => 'Reise'],
            ],
        ]);

        $this->assertStringContainsString('Arbeit', $html);
        $this->assertStringContainsString('Reise', $html);
        // Spaltensumme in aria-label und Σ-Spalte der Tabelle.
        $this->assertStringContainsString('KW 1: 35 h', $html);
        $this->assertStringContainsString('Σ', $html);
        $this->assertStringContainsString('fill-primary/70', $html);
    }

    public function test_stacked_bar_renders_empty_state_without_bands(): void {
        $html = Blade::render('<x-charts.stacked-bar :title="$t" unit="h" :series="[]" :bands="[]" />', ['t' => 'Leer']);

        $this->assertStringContainsString('Noch keine Daten', $html);
    }

    // ---- <x-charts.heatmap> --------------------------------------------

    public function test_heatmap_renders_matrix_with_totals_and_intensity(): void {
        $html = Blade::render('<x-charts.heatmap :title="$t" unit="h" :rows="$r" :col-labels="$c" x-label="Monat" />', [
            't' => 'Stunden pro Tag',
            'r' => [
                ['label' => 'Januar', 'cells' => [
                    ['value' => 120, 'title' => '01.01. — 2:00 h'],
                    null,
                ]],
                ['label' => 'Februar', 'cells' => [
                    ['value' => 0],
                    ['value' => 60, 'url' => '/reports/my-month?from=x'],
                ]],
            ],
            'c' => [1, 2],
        ]);

        $this->assertStringContainsString('Januar', $html);
        // Nicht vorhandene Zelle (31. Februar-Äquivalent) als neutraler Punkt.
        $this->assertStringContainsString('bg-base-200/40', $html);
        // Intensität über color-mix auf der Primary-Variable — kein Hex.
        $this->assertStringContainsString('color-mix(in oklab, var(--color-primary)', $html);
        // Zellen-Drilldown + Σ-Zeile/-Spalte.
        $this->assertStringContainsString('href="/reports/my-month?from=x"', $html);
        $this->assertStringContainsString('Σ', $html);
    }

    public function test_heatmap_renders_empty_state_when_all_zero(): void {
        $html = Blade::render('<x-charts.heatmap :title="$t" unit="h" :rows="$r" :col-labels="[1]" />', [
            't' => 'Leer',
            'r' => [['label' => 'Januar', 'cells' => [['value' => 0]]]],
        ]);

        $this->assertStringContainsString('Noch keine Daten', $html);
    }

    public function test_heatmap_uses_format_callable_for_cells_and_totals(): void {
        $html = Blade::render('<x-charts.heatmap :title="$t" unit="h" :rows="$r" :col-labels="[1]" :format="$f" />', [
            't' => 'Formatiert',
            'r' => [['label' => 'Januar', 'cells' => [['value' => 90]]]],
            'f' => fn(float $min): string => intdiv((int) $min, 60) . ':' . str_pad((string) ((int) $min % 60), 2, '0', STR_PAD_LEFT),
        ]);

        $this->assertStringContainsString('1:30', $html);
    }
}
