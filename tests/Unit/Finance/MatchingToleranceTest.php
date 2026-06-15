<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MatchingToleranceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Finance;

use App\Enums\Finance\AllocationKind;
use App\Services\Finance\MatchingService;
use PHPUnit\Framework\TestCase;

/**
 * Skonto-, Cent-Toleranz- und Voll/Teil/Über-Logik (Feature 045, Priorität 3).
 */
class MatchingToleranceTest extends TestCase {
    private MatchingService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->service = new MatchingService();
    }

    public function test_exact_amount_is_full_payment(): void {
        $this->assertSame(AllocationKind::Payment, $this->service->kindForInvoice(119.00, 119.00));
    }

    public function test_cent_tolerance_counts_as_full_payment(): void {
        // 2-Cent-Rundungsdifferenz unter dem offenen Betrag.
        $this->assertSame(AllocationKind::Payment, $this->service->kindForInvoice(118.98, 119.00));
    }

    public function test_skonto_within_tolerance_is_full_payment(): void {
        // 3 % Skonto auf 100,00 ⇒ 97,00 gilt als Vollzahlung, nicht Teilzahlung.
        $this->assertSame(AllocationKind::Payment, $this->service->kindForInvoice(97.00, 100.00));
    }

    public function test_underpayment_beyond_skonto_is_partial(): void {
        // 90,00 auf 100,00 liegt unter der 3-%-Skonto-Grenze (97,00).
        $this->assertSame(AllocationKind::Partial, $this->service->kindForInvoice(90.00, 100.00));
    }

    public function test_overpayment_is_flagged(): void {
        $this->assertSame(AllocationKind::Overpayment, $this->service->kindForInvoice(130.00, 119.00));
    }

    public function test_foreign_currency_is_never_full_payment(): void {
        $this->assertSame(AllocationKind::Partial, $this->service->kindForInvoice(119.00, 119.00, true));
    }
}
