<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InlineEventHandlerRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate gegen Inline-Event-Handler in Views (Vollscan 2026-08-23,
 * E10/I8): Unter der Nonce-CSP (SecurityHeaders: script-src 'self' 'nonce-…'
 * ohne 'unsafe-inline'/'unsafe-hashes') verwirft der Browser `onchange="…"` &
 * Co. still — der Artikel-Kategoriefilter (MVP-604) submittete deshalb nie.
 *
 * Ersatz: data-Attribute aus resources/js/inline-actions.js (data-autosubmit,
 * data-confirm …) oder Alpine-Direktiven (@click="name()").
 */
class InlineEventHandlerRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> */
    private const ALLOW_LIST = [
        'resources/views/legacy/' => 'Legacy-Modul ohne CSP-Nonce-Pflicht, wird abgelöst.',
        'resources/views/vendor/' => 'Fremd-Views (l5-swagger, Pagination-Vorlagen).',
    ];

    public function test_views_do_not_use_inline_event_handler_attributes(): void {
        $violations = [];

        foreach ($this->bladeFiles() as $file) {
            $relative = $this->relativePath($file);
            if ($this->isAllowListed($relative, self::ALLOW_LIST)) {
                continue;
            }

            $source = $this->stripBladeComments((string) file_get_contents($file));

            if (preg_match_all('/\s(on(?:click|dblclick|change|input|submit|reset|focus|blur|key(?:up|down|press)|mouse\w+|load|error|scroll|touch\w+|drag\w*|drop|paste|cut|copy|select|wheel|toggle))\s*=\s*["\']/i', $source, $matches, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }

            foreach ($matches[1] as [$attribute, $offset]) {
                $violations[] = sprintf('%s:%d — %s=', $relative, $this->lineOf($source, (int) $offset), $attribute);
            }
        }

        sort($violations);

        $this->assertSame([], $violations, "Inline-Event-Handler in einer View — unter der Nonce-CSP wirkungslos.\n"
            . "Stattdessen data-autosubmit/data-confirm (inline-actions.js) oder Alpine (@click=\"name()\").\n\n"
            . implode("\n", $violations));
    }
}
