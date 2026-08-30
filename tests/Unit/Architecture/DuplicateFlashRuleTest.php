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
 * Architektur-Gate „Flash-Dubletten" (Vollscan 2026-08-23, I4): Erfolgs-Flashes
 * (`session('success')`) rendert ausschließlich das Layout (layouts/app bzw.
 * customer/layout, jeweils mit role="status"/"alert") — lokale Blöcke in den
 * Views führten zur doppelten Anzeige derselben Meldung. Views dürfen den Key
 * daher nicht erneut rendern; `session('status')` bleibt bewusst view-lokal
 * (das App-Layout rendert ihn nicht).
 */
class DuplicateFlashRuleTest extends TestCase {
    use ScansSourceTree;

    private const PATTERN = '~session\(\s*[\'"]success[\'"]\s*\)~';

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
    ];

    public function test_views_do_not_render_success_flash_locally(): void {
        $violations = [];

        foreach ($this->bladeFiles() as $file) {
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
        $this->assertSame([], $violations, "Lokaler session('success')-Render-Block: Das Layout zeigt Erfolgs-Flashes\n"
            . "bereits zentral (layouts/app bzw. customer/layout) — der lokale Block erzeugt eine Dublette.\n"
            . "Block entfernen; begründete Ausnahmen (eigenes/kein Layout) in die ALLOW_LIST.\n\n"
            . implode("\n", $violations));
    }
}
