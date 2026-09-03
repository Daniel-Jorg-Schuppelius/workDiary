<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleCatalogTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Reselling;

use App\Services\Reselling\Marketplace\{ArticleCatalog, ArticleEntry, InvoiceLine};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Tests\TestCase;

class ArticleCatalogTest extends TestCase {
    private function catalog(): ArticleCatalog {
        return new ArticleCatalog([
            new ArticleEntry('art-exo', 'DJS-IT-MCLD-EX01P1', 'Exchange Online (Plan 1)', 'Monat', Money::of('3.95', CurrencyCode::Euro)),
            new ArticleEntry('art-bp', 'DCF-IT-MCLD-O001BP', 'Microsoft 365 Business Premium', 'Monat', Money::of('20.60', CurrencyCode::Euro)),
            new ArticleEntry('art-box', 'DJS-IT-MSOF-OFHB21', 'Office Home & Business 2021', 'Stück', Money::of('251.26', CurrencyCode::Euro)),
            new ArticleEntry('art-sup', 'SGIT-IT-DSBB-001YE', 'Business Support', 'Monat', Money::of('700.00', CurrencyCode::Euro)),
        ]);
    }

    private function line(string $name, string $articleId = ''): InvoiceLine {
        return new InvoiceLine('v', 'RE-1', CarbonImmutable::parse('2026-01-01'), 'invoice', 'c-1', 1, $name, '', 12.0, Money::of('3.95', CurrencyCode::Euro), false, '', '', $articleId);
    }

    public function test_line_is_resolved_by_article_id_number_or_name(): void {
        $catalog = $this->catalog();

        $this->assertSame('Exchange Online (Plan 1)', $catalog->forLine($this->line('irgendwas', 'art-exo'))?->name, 'Artikel-ID gewinnt');
        $this->assertSame('Exchange Online (Plan 1)', $catalog->forLine($this->line('[DJS-IT-MCLD-EX01P1] Exchange Online'))?->name, 'Artikelnummer in Klammern');
        $this->assertSame('Microsoft 365 Business Premium', $catalog->forLine($this->line('Microsoft 365 Business Premium'))?->name, 'Name exakt');
        $this->assertNull($catalog->forLine($this->line('Freitextposition')));
        $this->assertSame('DJS-IT-MCLD-EX01P1', $this->line('[DJS-IT-MCLD-EX01P1] Exchange Online')->articleNumber());
    }

    public function test_monthly_unit_and_term_price(): void {
        $catalog = $this->catalog();
        $premium = $catalog->forLine($this->line('', 'art-bp'));
        $this->assertNotNull($premium);
        $this->assertTrue($premium->isMonthly());
        $this->assertSame(24720, $premium->priceForTerm(12)?->getMinorAmount(), '20,60 € × 12');

        $box = $catalog->forLine($this->line('', 'art-box'));
        $this->assertNotNull($box);
        $this->assertFalse($box->isMonthly());
        $this->assertSame(25126, $box->priceForTerm(12)?->getMinorAmount(), 'Stückpreis bleibt');
    }

    public function test_articles_for_an_edition(): void {
        $names = array_map(static fn(ArticleEntry $e): string => $e->name, $this->catalog()->forEdition('Exchange Online (Plan 1)'));
        $this->assertSame(['Exchange Online (Plan 1)'], $names);
        $this->assertSame([], $this->catalog()->forEdition('Microsoft Teams Essentials'));
    }
}
