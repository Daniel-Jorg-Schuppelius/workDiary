<?php
/*
 * Created on   : Sat Oct 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainNamesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\DomainNames;
use PHPUnit\Framework\TestCase;

/**
 * Domainnamen in Texten (Feature 185, MVP-1442): exakte Nennung, beide
 * IDN-Formen, lesbare Bezeichnung.
 */
class DomainNamesTest extends TestCase {
    public function test_forms_carry_both_idn_spellings(): void {
        $this->assertSame(['xn--onlinehndler-beispiel-c2b.de', 'onlinehändler-beispiel.de'], DomainNames::forms('XN--onlinehndler-beispiel-c2b.de'));
        $this->assertSame(['onlinehändler-beispiel.de', 'xn--onlinehndler-beispiel-c2b.de'], DomainNames::forms('onlinehändler-beispiel.de'));
        $this->assertSame(['beispiel-kanzlei.de'], DomainNames::forms(' beispiel-kanzlei.de '));
        $this->assertSame([], DomainNames::forms(''));
        $this->assertSame('onlinehändler-beispiel.de', DomainNames::display('xn--onlinehndler-beispiel-c2b.de'));
        $this->assertSame('beispiel-kanzlei.de', DomainNames::display('Beispiel-Kanzlei.de'));
        $this->assertSame('xn--onlinehndler-beispiel-c2b.de', DomainNames::ascii('onlinehändler-beispiel.de'));
    }

    public function test_mentioned_requires_the_exact_domain_as_its_own_word(): void {
        $text = 'Top-Level-Domain .de-Domain inkl. Verwaltung (beispiel-kanzlei.de)';
        $this->assertTrue(DomainNames::mentioned($text, 'beispiel-kanzlei.de'));
        $this->assertTrue(DomainNames::mentioned($text, 'BEISPIEL-KANZLEI.DE'));
        $this->assertFalse(DomainNames::mentioned($text, 'beispiel-kanzlei-berlin.de'), 'längere Domain ist eine andere');
        $this->assertFalse(DomainNames::mentioned('… (beispiel-kanzlei-berlin.de)', 'beispiel-kanzlei.de'), 'kürzere Domain ist keine Teilzeichenkette der längeren');
        $this->assertFalse(DomainNames::mentioned('… (shop.beispiel-kanzlei.de)', 'beispiel-kanzlei.de'), 'Subdomain ist nicht die Domain');
        $this->assertTrue(DomainNames::mentioned('Domain Jahresgebühr => onlinehändler-beispiel.de => 2017', 'xn--onlinehndler-beispiel-c2b.de'), 'Rechnung schreibt Unicode, Anbieter führt Punycode');
        $this->assertTrue(DomainNames::mentioned('Verlängerung xn--onlinehndler-beispiel-c2b.de', 'onlinehändler-beispiel.de'), 'umgekehrt ebenso');
    }

    public function test_tokens_list_every_domain_once(): void {
        $this->assertSame(['beispiel-kanzlei.de', 'beispiel-kanzlei-berlin.de'], DomainNames::tokens('beispiel-kanzlei.de und Beispiel-Kanzlei-Berlin.de, nochmal beispiel-kanzlei.de'));
        $this->assertSame([], DomainNames::tokens('Business Support 2 Stunden'));
    }
}
