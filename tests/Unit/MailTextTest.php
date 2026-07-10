<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailTextTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit;

use App\Support\MailText;
use PHPUnit\Framework\TestCase;

/**
 * Whitebox 2026-07-10 (J8): Nutzertext in Markdown-Mail-Lines darf keine
 * klickbaren Links erzeugen (Phishing über Spesen-Beschreibung/Eventtitel).
 */
class MailTextTest extends TestCase {
    public function test_link_syntax_is_neutralized(): void {
        $out = MailText::plain('Bitte [Passwort prüfen](https://evil.example) sofort');

        $this->assertSame('Bitte \\[Passwort prüfen\\](https://evil.example) sofort', $out);
    }

    public function test_backslash_cannot_unescape(): void {
        // Ein vorangestellter Backslash darf das Escaping nicht aushebeln.
        $out = MailText::plain('\\[x](https://evil.example)');

        $this->assertSame('\\\\\\[x\\](https://evil.example)', $out);
    }

    public function test_plain_text_stays_readable(): void {
        $this->assertSame('Schrauben 4x30, verzinkt', MailText::plain('Schrauben 4x30, verzinkt'));
        $this->assertSame('', MailText::plain(null));
    }
}
