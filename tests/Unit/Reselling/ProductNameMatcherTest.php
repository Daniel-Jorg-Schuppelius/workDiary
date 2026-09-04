<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProductNameMatcherTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Reselling;

use App\Services\Reselling\Marketplace\ProductNameMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductNameMatcherTest extends TestCase {
    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function cases(): array {
        return [
            'exact edition' => ['Microsoft 365 Business Standard', 'Microsoft 365 Business Standard Jahreslizenz', true],
            'abbreviated m365' => ['Microsoft 365 Business Standard', 'M365 Business Standard 12 Monate', true],
            'standard is not premium' => ['Microsoft 365 Business Standard', 'Microsoft 365 Business Premium', false],
            'premium is not standard' => ['Microsoft 365 Business Premium', 'M365 Business Standard', false],
            'exchange with plan' => ['Exchange Online (Plan 1)', 'Exchange Online Plan 1 – Postfach', true],
            'exchange short alias' => ['Exchange Online (Plan 1)', 'Exchange Online Postfach', true],
            'exchange in description' => ['Exchange Online (Plan 1)', 'Lizenz | Microsoft Exchange Online (Plan 1) für 1 Nutzer', true],
            'apps for business' => ['Microsoft 365 Apps for business', 'Microsoft 365 Apps for Business', true],
            'teams essentials' => ['Microsoft Teams Essentials', 'MS Teams Essentials Jahresabo', true],
            'unrelated service' => ['Microsoft 365 Business Standard', 'Fernwartung 2 Stunden', false],
            'e5 eea' => ['Office 365 E5 EEA (no Teams)', 'Office 365 E5 (EEA, ohne Teams)', true],
        ];
    }

    #[DataProvider('cases')]
    public function test_matches(string $edition, string $text, bool $expected): void {
        $this->assertSame($expected, (new ProductNameMatcher)->matches($edition, $text));
    }

    public function test_tokens_drop_filler_words(): void {
        $this->assertSame(['exchange', 'online', 'plan', '1'], (new ProductNameMatcher)->tokens('Exchange Online (Plan 1)'));
        $this->assertSame(['365', 'business', 'standard'], (new ProductNameMatcher)->tokens('Microsoft 365 Business Standard'));
    }

    public function test_microsoft_hint_detection(): void {
        $matcher = new ProductNameMatcher;
        $this->assertTrue($matcher->looksLikeMicrosoftProduct('Exchange Online Plan 2'));
        $this->assertTrue($matcher->looksLikeMicrosoftProduct('M365 Business Basic'));
        $this->assertFalse($matcher->looksLikeMicrosoftProduct('Fernwartung 2 Stunden'));
        // Eigene Leistungen mit „Business" oder „Microsoft" im Text sind keine Lizenzen.
        $this->assertFalse($matcher->looksLikeMicrosoftProduct('Business Support'));
        $this->assertFalse($matcher->looksLikeMicrosoftProduct('[SGIT-IT-DSBB-00001HO] - Business Support'));
        $this->assertFalse($matcher->looksLikeMicrosoftProduct('Business Support für Microsoft 365 Umgebung'));
        $this->assertFalse($matcher->looksLikeMicrosoftProduct('Einrichtung Microsoft 365 Tenant, 2 Stunden'));
        // Eigene „Business …"-Leistungen dürfen nie über „Business Premium/Standard/Basic" zünden.
        $this->assertFalse($matcher->looksLikeMicrosoftProduct('Business Premium'));
        $this->assertFalse($matcher->looksLikeMicrosoftProduct('Business Standard Betreuung'));
        $this->assertFalse($matcher->looksLikeMicrosoftProduct('Business Basic Paket'));
        $this->assertFalse($matcher->looksLikeMicrosoftProduct('[SGIT-IT-DSBB-01PRE] - Business Support Premium'));
        $this->assertTrue($matcher->looksLikeMicrosoftProduct('Microsoft 365 Business Premium'));
    }
}
