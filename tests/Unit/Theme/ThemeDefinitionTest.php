<?php
/*
 * Created on   : Sat Jun 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ThemeDefinitionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Theme;

use App\Support\ThemeDefinition;
use Tests\TestCase;

class ThemeDefinitionTest extends TestCase {
    /** @return array<string, string> */
    private function validColors(): array {
        return [
            'base-100' => '#ffffff', 'base-200' => '#f1f5f9', 'base-300' => '#e2e8f0',
            'primary' => '#0284c7', 'secondary' => '#475569', 'accent' => '#0d9488', 'neutral' => '#1e293b',
        ];
    }

    public function test_builds_a_valid_definition_and_token(): void {
        $def = ThemeDefinition::fromArray([
            'key' => 'ocean', 'label' => 'Ocean Blue', 'scheme' => 'light',
            'colors' => $this->validColors(),
        ]);

        $this->assertNotNull($def);
        $this->assertSame('ocean', $def->key);
        $this->assertSame('org-ocean', $def->token());
        $this->assertSame('light', $def->scheme);
    }

    public function test_missing_required_color_yields_null(): void {
        $colors = $this->validColors();
        unset($colors['primary']);

        $this->assertNull(ThemeDefinition::fromArray([
            'key' => 'x', 'label' => 'X', 'scheme' => 'light', 'colors' => $colors,
        ]));
    }

    public function test_rejects_key_with_css_metacharacters(): void {
        $this->assertNull(ThemeDefinition::fromArray([
            'key' => 'evil"]{}', 'label' => 'x', 'scheme' => 'light', 'colors' => $this->validColors(),
        ]));
    }

    public function test_rejects_injection_in_required_color(): void {
        $colors = $this->validColors();
        $colors['base-100'] = '#000;}body{display:none}';

        $this->assertNull(ThemeDefinition::fromArray([
            'key' => 'evil', 'label' => 'x', 'scheme' => 'light', 'colors' => $colors,
        ]));
    }

    public function test_css_only_contains_allowlisted_tokens(): void {
        $colors = $this->validColors();
        // Versuch, eine fremde Property einzuschleusen — muss ignoriert werden.
        $colors['display'] = 'none';
        $colors['primary-content'] = 'red; pointer-events:none';

        $def = ThemeDefinition::fromArray([
            'key' => 'ocean', 'label' => 'Ocean', 'scheme' => 'light', 'colors' => $colors,
        ]);
        $css = $def->toCss();

        $this->assertStringStartsWith('[data-theme="org-ocean"]{', $css);
        $this->assertStringNotContainsString('display:none', $css);
        $this->assertStringNotContainsString('pointer-events', $css);
        // Das ungültige primary-content wird per Kontrast abgeleitet (schwarz/weiß).
        $this->assertMatchesRegularExpression('/--color-primary-content:#(1f2937|ffffff);/', $css);
        $this->assertStringEndsWith('}', $css);
    }

    public function test_derives_content_colors_by_contrast(): void {
        $def = ThemeDefinition::fromArray([
            'key' => 'c', 'label' => 'C', 'scheme' => 'light',
            'colors' => $this->validColors(),
        ]);
        $vars = $def->toCssVars();

        // Heller Hintergrund → dunkler Text; dunkles neutral → heller Text.
        $this->assertSame('#1f2937', $vars['--color-base-content']);
        $this->assertSame('#ffffff', $vars['--color-neutral-content']);
        $this->assertSame('light', $vars['color-scheme']);
    }

    public function test_explicit_content_color_is_respected(): void {
        $colors = $this->validColors();
        $colors['primary-content'] = '#abcdef';

        $def = ThemeDefinition::fromArray([
            'key' => 'c', 'label' => 'C', 'scheme' => 'light', 'colors' => $colors,
        ]);

        $this->assertSame('#abcdef', $def->toCssVars()['--color-primary-content']);
    }

    public function test_geometry_falls_back_to_config_defaults_for_invalid_units(): void {
        $def = ThemeDefinition::fromArray([
            'key' => 'c', 'label' => 'C', 'scheme' => 'dark',
            'colors' => $this->validColors(),
            'geometry' => ['radius-box' => 'javascript:alert(1)', 'radius-field' => '0.25rem'],
        ]);
        $vars = $def->toCssVars();

        $this->assertSame((string) config('theme.geometry.radius-box'), $vars['--radius-box']);
        $this->assertSame('0.25rem', $vars['--radius-field']);
    }
}
