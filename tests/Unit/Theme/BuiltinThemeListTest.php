<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BuiltinThemeListTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Theme;

use Tests\TestCase;

/**
 * config('theme.builtin') und der CSS-Build müssen dieselben Themes kennen —
 * sonst validiert ein Theme, hat aber kein CSS.
 */
class BuiltinThemeListTest extends TestCase {
    /** @return array<string, ?string> data-theme-Wert → color-scheme (null = daisyUI-Theme aus der Liste) */
    private function cssThemes(): array {
        $css = (string) file_get_contents(resource_path('css/app.css'));
        $themes = [];

        preg_match("~@plugin '[^']*daisyui/index\.js'\s*\{\s*themes:([^;]+);~", $css, $list);
        foreach (explode(',', $list[1] ?? '') as $entry) {
            $name = strtok(trim($entry), ' ');
            if (is_string($name)) {
                $themes[$name] = null;
            }
        }

        preg_match_all("~@plugin '[^']*daisyui/theme/index\.js'\s*\{([^}]*)\}~", $css, $blocks);
        foreach ($blocks[1] as $block) {
            preg_match('~name:\s*"([^"]+)"~', $block, $name);
            preg_match('~color-scheme:\s*(light|dark)~', $block, $scheme);
            $themes[$name[1] ?? ''] = $scheme[1] ?? null;
        }

        return $themes;
    }

    public function test_config_and_css_list_the_same_themes(): void {
        $css = $this->cssThemes();
        $config = (array) config('theme.builtin');

        $this->assertEqualsCanonicalizing(array_keys($config), array_keys($css));
        foreach ($css as $name => $scheme) {
            if ($scheme !== null) {
                $this->assertSame($scheme, $config[$name]['scheme'] ?? null, "color-scheme von {$name}");
            }
        }
    }

    public function test_auto_pair_points_to_builtin_themes_of_the_right_scheme(): void {
        $config = (array) config('theme.builtin');

        $this->assertSame('light', $config[config('theme.auto.light')]['scheme'] ?? null);
        $this->assertSame('dark', $config[config('theme.auto.dark')]['scheme'] ?? null);
    }
}
