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
        // MVP-469: Leerzustand zentriert sich in gestreckten Grid-Kacheln.
        $this->assertStringContainsString('wd-chart-empty', $html);
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
        // Gleichwertige Tabelle unterhalb des Diagramms — füllt in
        // chart-grid-Kacheln die Resthöhe (MVP-469, Klasse wd-chart-table).
        $this->assertStringContainsString('Zweites Projekt', $html);
        $this->assertStringContainsString('wd-chart-table', $html);
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

    public function test_stacked_bar_hatches_contrast_band_instead_of_second_color(): void {
        $html = Blade::render('<x-charts.stacked-bar :title="$t" unit="h" :series="$s" :bands="$b" />', [
            't' => 'Abrechenbar vs. nicht abrechenbar',
            's' => [['x' => 'Jan', 'billable' => 30, 'non_billable' => 10]],
            'b' => [
                ['key' => 'billable', 'label' => 'Abrechenbar'],
                ['key' => 'non_billable', 'label' => 'Nicht abrechenbar', 'hatch' => true],
            ],
        ]);

        // Kontrastband als Schraffur (Farbe nie alleiniger Träger) — Segment
        // UND Legenden-Kästchen; das schraffierte Band bekommt KEINE
        // Themen-Füllfarbe aus der Leiter.
        $this->assertStringContainsString('hatch-sb-', $html);
        $this->assertMatchesRegularExpression('/<rect[^>]*fill="url\(#hatch-sb-[^"]+\)"[^>]*class="stroke-secondary"/', $html);
        $this->assertStringContainsString('fill-primary/70', $html);
        $this->assertStringNotContainsString('fill-secondary/60', $html);
        $this->assertDoesNotMatchRegularExpression('/(?:fill|stroke|background)[^"]*#[0-9a-f]{3,6}/i', $html);
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

    // ---- <x-charts.bullet> ---------------------------------------------

    public function test_bullet_renders_empty_state_without_data(): void {
        $html = Blade::render('<x-charts.bullet :title="$t" unit="%" :series="[]" />', ['t' => 'Auslastung']);

        $this->assertStringContainsString('Noch keine Daten', $html);
        $this->assertStringNotContainsString('<svg viewBox', $html);
    }

    public function test_bullet_renders_target_marker_bands_and_attainment(): void {
        $html = Blade::render('<x-charts.bullet :title="$t" unit="%" :series="$s" />', [
            't' => 'Auslastung je Team',
            's' => [
                ['x' => 'Team Nord', 'y' => 72.5, 'target' => 80, 'bands' => [50, 70], 'url' => '/reports/utilization?team=abc'],
                ['x' => 'Team Süd', 'y' => 40],
            ],
        ]);

        $this->assertStringContainsString('aria-label="Team Nord: 72.5 %, Ziel: 80 %"', $html);
        $this->assertStringContainsString('href="/reports/utilization?team=abc"', $html);
        $this->assertStringContainsString('tabindex="0"', $html);
        $this->assertStringContainsString('fill-secondary', $html);
        $this->assertStringContainsString('fill-base-300', $html);
        // Erreichung in der gleichwertigen Tabelle; ohne Ziel ehrlich „—".
        $this->assertStringContainsString('91%', $html);
        $this->assertStringContainsString('Team Süd', $html);
        $this->assertDoesNotMatchRegularExpression('/(?:fill|stroke|background)[^"]*#[0-9a-f]{3,6}/i', $html);
    }

    // ---- <x-charts.waterfall> ------------------------------------------

    public function test_waterfall_renders_empty_state_without_deltas(): void {
        $html = Blade::render('<x-charts.waterfall :title="$t" unit="Kunden" :series="[]" :start-value="10" />', ['t' => 'Brücke']);

        $this->assertStringContainsString('Noch keine Daten', $html);
    }

    public function test_waterfall_renders_totals_deltas_and_running_balance(): void {
        $html = Blade::render('<x-charts.waterfall :title="$t" unit="Kunden" :series="$s" :start-value="10" start-label="Bestand 2025" end-label="Bestand 2026" />', [
            't' => 'Kundenbestandsbrücke',
            's' => [
                ['x' => 'Neukunden', 'y' => 4, 'url' => '/reports/customer-retention?list=new'],
                ['x' => 'Verloren', 'y' => -3],
            ],
        ]);

        $this->assertStringContainsString('Bestand 2025', $html);
        $this->assertStringContainsString('Bestand 2026', $html);
        // Δ mit Vorzeichen + kumulierter Stand in aria-label und Tabelle.
        $this->assertStringContainsString('aria-label="Neukunden: +4 Kunden, Stand: 14"', $html);
        $this->assertStringContainsString('aria-label="Verloren: −3 Kunden, Stand: 11"', $html);
        $this->assertStringContainsString('href="/reports/customer-retention?list=new"', $html);
        // Abnahme schraffiert (Farbe nie alleiniger Träger) + Legende.
        $this->assertStringContainsString('hatch-wf-', $html);
        $this->assertStringContainsString('Zunahme', $html);
        $this->assertStringContainsString('Abnahme', $html);
        $this->assertDoesNotMatchRegularExpression('/(?:fill|stroke|background)[^"]*#[0-9a-f]{3,6}/i', $html);
    }

    // ---- <x-charts.boxplot> --------------------------------------------

    public function test_boxplot_renders_empty_state_without_data(): void {
        $html = Blade::render('<x-charts.boxplot :title="$t" unit="Tage" :series="[]" />', ['t' => 'Zahldauer']);

        $this->assertStringContainsString('Noch keine Daten', $html);
    }

    public function test_boxplot_renders_five_number_summary_and_table(): void {
        $html = Blade::render('<x-charts.boxplot :title="$t" unit="Tage" :series="$s" />', [
            't' => 'Zahldauer je Kunde',
            's' => [
                ['x' => 'Kunde A', 'min' => 2, 'q1' => 5, 'median' => 9.5, 'q3' => 14, 'max' => 30, 'n' => 12, 'url' => '/reports/payment-behavior?customer=abc'],
            ],
        ]);

        $this->assertStringContainsString('Median 9.5 Tage', $html);
        $this->assertStringContainsString('Quartile 5–14', $html);
        $this->assertStringContainsString('n=12', $html);
        $this->assertStringContainsString('href="/reports/payment-behavior?customer=abc"', $html);
        $this->assertStringContainsString('fill-primary/30', $html);
        $this->assertDoesNotMatchRegularExpression('/(?:fill|stroke|background)[^"]*#[0-9a-f]{3,6}/i', $html);
    }

    // ---- note-Prop (MVP-470) -------------------------------------------

    public function test_note_prop_renders_data_basis_hint(): void {
        $html = Blade::render('<x-charts.bar-h :title="$t" unit="h" :series="$s" :note="$n" />', [
            't' => 'Mit Hinweis',
            's' => [['x' => 'A', 'y' => 1]],
            'n' => 'Datenbasis: abrechenbare Zeit-Snapshots.',
        ]);

        $this->assertStringContainsString('Datenbasis: abrechenbare Zeit-Snapshots.', $html);
    }

    // ---- <x-charts.sparkline> ------------------------------------------

    public function test_sparkline_renders_dash_without_values(): void {
        $html = Blade::render('<x-charts.sparkline :values="[]" />');

        $this->assertStringContainsString('—', $html);
        $this->assertStringNotContainsString('<svg', $html);
    }

    public function test_sparkline_renders_inline_trend_with_aria_summary(): void {
        $html = Blade::render('<x-charts.sparkline :values="$v" unit="h" label="Monatsstunden" />', [
            'v' => [10, 12.5, 8, 15],
        ]);

        // Kontrakt-Sonderfall: kein figure, aber Werte-Zugang über aria-label.
        $this->assertStringNotContainsString('<figure', $html);
        $this->assertStringContainsString('Monatsstunden: zuletzt 15 h (Min 8, Max 15)', $html);
        $this->assertStringContainsString('stroke-primary', $html);
        $this->assertDoesNotMatchRegularExpression('/(?:fill|stroke|background)[^"]*#[0-9a-f]{3,6}/i', $html);
    }
}
