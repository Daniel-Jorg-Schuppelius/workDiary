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
            'word order ignored' => ['Fliesen Urban', 'Urban Fliesen', true],
            'ampersand vs und' => ['Meyer&Simson Elektroinstallationen GmbH', 'Meyer und Simson', true],
            'association suffix' => ['Kindheit', 'Kindheit e.V.', true],
            'surname inside long name' => ['Frank Hüpperling und Stephan Vieweger Architekten Partnerschaftsgesellschaft mbB', 'Hüpperling', true],
            'hyphenated' => ['Party Service Otte GmbH', 'Party-Service-Otte GmbH', true],
            'different firm same industry' => ['VTM Versorgungstechnik GmbH', 'Zain Versorgungstechnik', false],
            'short token only' => ['GSR Berlin GmbH', 'GSR Gebäude-Service GmbH', false],
            'unrelated' => ['Weber Marking Systems', 'TROTEC Leipzig/Weber M.S.', false],
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
        $this->assertSame(['meyer', 'simson', 'elektroinstallationen'], NameTokenMatcher::significantTokens('Meyer&Simson Elektroinstallationen GmbH'));
    }
}
