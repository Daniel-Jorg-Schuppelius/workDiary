<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FrontendHttpBoundaryRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „HTTP-Grenze im Frontend" (Vollscan 2026-08-23, I14 /
 * MVP-724): Alle Requests laufen über `resources/js/lib/http.js`
 * (request/getJson/postJson/…/submitForm). Dort hängen CSRF-Token aus dem
 * Meta-Tag, `credentials: "same-origin"`, die einheitliche 419-Behandlung
 * (Hinweis + Reload, Hintergrund-Sync via `on419: "ignore"`) und das
 * 422-Fehlerobjekt.
 *
 * Daraus folgen zwei Regeln:
 *  1. Kein rohes `fetch(` außerhalb von lib/http.js — sonst fehlen CSRF und
 *     419-Behandlung, und ein abgelaufenes Login endet als stiller Fehler.
 *  2. Kein `csrf_token()` in Blade-Attributen (`data-csrf="…"`) und kein
 *     Token-Durchreichen über JS-Konfigurationsobjekte (`_cfg.csrf`,
 *     `config.csrf`, `dataset.csrf`) — das Token kommt aus dem Meta-Tag.
 *     `@csrf` in echten `<form>`-Elementen bleibt selbstverständlich erlaubt.
 */
class FrontendHttpBoundaryRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> Pfad-Präfix → Begründung */
    private const FETCH_ALLOW_LIST = [
        'resources/js/lib/http.js' => 'Die HTTP-Naht selbst.',
        'resources/js/sw.js' => 'Service-Worker: eigener fetch-Handler/Cache, außerhalb des DOM-Kontexts.',
    ];

    public function test_frontend_requests_go_through_the_http_boundary(): void {
        $violations = [];

        foreach ($this->filesUnder('resources/js', '/\.(?:js|mjs)$/') as $file) {
            $relative = $this->relativePath($file);
            if ($this->isAllowListed($relative, self::FETCH_ALLOW_LIST)) {
                continue;
            }

            $source = $this->stripComments((string) file_get_contents($file));
            if (preg_match_all('/(?<![.\w])fetch\s*\(/', $source, $matches, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }

            foreach ($matches[0] as [$_, $offset]) {
                $violations[] = sprintf('%s:%d — rohes fetch()', $relative, $this->lineOf($source, (int) $offset));
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "Rohes fetch() im Frontend — CSRF/419/422 fehlen.\n"
            . "Stattdessen request()/getJson()/postJson()/submitForm() aus resources/js/lib/http.js importieren.\n\n"
            . implode("\n", $violations));
    }

    public function test_views_do_not_hand_the_csrf_token_to_javascript(): void {
        $violations = [];
        $files = array_merge(
            $this->bladeFiles(),
            $this->filesUnder('app/Plugins', '/\.blade\.php$/'),
            $this->filesUnder('resources/js', '/\.(?:js|mjs)$/'),
        );

        foreach ($files as $file) {
            $relative = $this->relativePath($file);
            if (str_starts_with($relative, 'resources/js/lib/http.js')) {
                continue;
            }

            $source = str_ends_with($relative, '.blade.php')
                ? $this->stripBladeComments((string) file_get_contents($file))
                : $this->stripComments((string) file_get_contents($file));

            $patterns = [
                '/data-csrf\s*=/' => 'data-csrf-Attribut',
                '/["\']?csrf["\']?\s*:\s*["\']?\{\{\s*csrf_token/' => 'csrf im JS-Konfig-Objekt',
                '/\bdataset\.csrf\b/' => 'dataset.csrf',
                '/\b_cfg\.csrf\b/' => '_cfg.csrf',
            ];

            foreach ($patterns as $pattern => $label) {
                if (preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE) === 0) {
                    continue;
                }
                foreach ($matches[0] as [$_, $offset]) {
                    $violations[] = sprintf('%s:%d — %s', $relative, $this->lineOf($source, (int) $offset), $label);
                }
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "CSRF-Token wird an JavaScript durchgereicht.\n"
            . "lib/http.js liest es aus <meta name=\"csrf-token\">; das Attribut ersatzlos streichen.\n\n"
            . implode("\n", $violations));
    }
}
