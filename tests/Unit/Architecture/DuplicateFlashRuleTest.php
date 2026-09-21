<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DuplicateFlashRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „Flash-Dubletten" (Vollscan 2026-08-23, I4): Flashes
 * (success/status/error/warning/info) rendert ausschließlich das Layout
 * (layouts/app bzw. customer/layout, jeweils mit role="status"/"alert") —
 * lokale Blöcke führten zur doppelten Anzeige derselben Meldung. Seit dem
 * UI-Fuzz 2026-09-21 rendert das App-Layout auch `status`; vorher zeigten nur
 * einzelne Views ihn, alle übrigen with('status')-Meldungen gingen verloren.
 * Plugin-Views (app/Plugins) zählen mit.
 */
class DuplicateFlashRuleTest extends TestCase {
    use ScansSourceTree;

    private const PATTERN = '~session\(\s*[\'"](success|status|error|warning|info)[\'"]\s*\)~';

    /** @var array<string, string> Pfad-Präfix → Begründung */
    private const ALLOW_LIST = [
        // Layout-Dateien außerhalb resources/views/layouts: SIND der zentrale Mechanismus.
        'resources/views/customer/layout.blade.php' => 'Portal-Layout, zentrale Flash-Stelle (role gesetzt)',
        // Guest-Layout rendert keine Flashes zentral — die Seite ist selbst zuständig.
        'resources/views/auth/two-factor-challenge.blade.php' => 'layouts.guest ohne zentralen Flash (role="status" lokal)',
        // Standalone-/öffentliche Seiten ohne App-Layout.
        'resources/views/public/' => 'öffentliche Seiten ohne App-Layout (role="status" lokal)',
        'resources/views/learning/external/' => 'Lernzugang ohne Konto auf layouts.guest — kein zentraler Flash (role="status" lokal)',
        'resources/views/whistleblowing/public/' => 'eigenes öffentliches Layout (wb-card, role="status" lokal)',
        'resources/views/auth/' => 'layouts.guest ohne zentralen Flash (Login/Passwort vergessen)',
        'resources/views/account/password.blade.php' => 'layouts.guest (erzwungener Passwortwechsel)',
        'resources/views/account/_password_dialog.blade.php' => 'Dialogfragment: Hinweis zum erzwungenen Passwortwechsel im Dialog selbst',
        'resources/views/quotes/portal.blade.php' => 'eigenständige Angebotsseite ohne App-Layout',
        'resources/views/careers/layout.blade.php' => 'eigenes öffentliches Karriere-Layout, zentrale Flash-Stelle',
    ];

    public function test_views_do_not_render_layout_flashes_locally(): void {
        $violations = [];

        foreach ([...$this->bladeFiles(), ...$this->bladeFiles('app/Plugins')] as $file) {
            $relative = $this->relativePath($file);
            if (str_starts_with($relative, 'resources/views/layouts/')
                || str_starts_with($relative, 'resources/views/components/')
                || $this->isAllowListed($relative, self::ALLOW_LIST)) {
                continue;
            }

            $source = $this->stripBladeComments((string) file_get_contents($file));
            if (preg_match(self::PATTERN, $source, $m, PREG_OFFSET_CAPTURE) === 1) {
                $violations[] = sprintf('%s:%d', $relative, $this->lineOf($source, (int) $m[0][1]));
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "Lokaler Flash-Render-Block (success/status/error/warning/info): Das Layout zeigt Flashes\n"
            . "bereits zentral (layouts/app bzw. customer/layout) — der lokale Block erzeugt eine Dublette.\n"
            . "Block entfernen; begründete Ausnahmen (eigenes/kein Layout) in die ALLOW_LIST.\n\n"
            . implode("\n", $violations));
    }
}
