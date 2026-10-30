<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NameTokenMatcherTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Reselling;

use App\Services\Reselling\Marketplace\NameTokenMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NameTokenMatcherTest extends TestCase {
    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function pairs(): array {
        return [
            'legal form ignored' => ['Auerswald GmbH & Co. KG', 'Auerswald', true],
            'word order ignored' => ['Fliesen Wendt', 'Wendt Fliesen', true],
            'ampersand vs und' => ['Berg&Tal Elektroinstallationen GmbH', 'Berg und Tal', true],
            'association suffix' => ['Jugendhilfe', 'Jugendhilfe e.V.', true],
            'surname inside long name' => ['Frank Ahorn und Stephan Birke Architekten Partnerschaftsgesellschaft mbB', 'Ahorn', true],
            'hyphenated' => ['Party Service Wiese GmbH', 'Party-Service-Wiese GmbH', true],
            'different firm same industry' => ['HTB Versorgungstechnik GmbH', 'Quell Versorgungstechnik', false],
            'short token only' => ['HGS Berlin GmbH', 'HGS Gebäude-Service GmbH', false],
            'unrelated' => ['Falke Marking Systems', 'LASERTEC Leipzig/Falke M.S.', false],
            'generic only' => ['Service GmbH', 'Haus Service', false],
        ];
    }

    #[DataProvider('pairs')]
    public function test_matches(string $a, string $b, bool $expected): void {
        $this->assertSame($expected, NameTokenMatcher::matches($a, $b));
        $this->assertSame($expected, NameTokenMatcher::matches($b, $a), 'symmetrisch');
    }

    public function test_significant_tokens_drop_legal_forms_and_fillers(): void {
        $this->assertSame(['auerswald'], NameTokenMatcher::significantTokens('Auerswald GmbH & Co. KG'));
        $this->assertSame(['berg', 'tal', 'elektroinstallationen'], NameTokenMatcher::significantTokens('Berg&Tal Elektroinstallationen GmbH'));
    }
}
