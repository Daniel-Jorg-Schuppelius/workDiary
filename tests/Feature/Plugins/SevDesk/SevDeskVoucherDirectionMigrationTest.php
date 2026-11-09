<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskVoucherDirectionMigrationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins\SevDesk;

use App\Models\Finance\AccountingVoucher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-1520: Bestand der sevDesk-Belege auf die dokumentierte Richtung (C = Ausgabe, D = Einnahme). */
final class SevDeskVoucherDirectionMigrationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function migration(): Migration {
        return require database_path('migrations/2027_04_11_100000_correct_sevdesk_voucher_direction.php');
    }

    /** @param array<string, mixed> $attributes */
    private function voucher(string $externalId, array $attributes): AccountingVoucher {
        return AccountingVoucher::factory()->create($attributes + [
            'organization_id' => $this->organization->id,
            'plugin_id' => 'sevdesk',
            'external_id' => $externalId,
            'document_kind' => 'other',
            'open_amount' => null,
            'payload' => [],
        ]);
    }

    public function test_flips_voucher_direction_and_fills_open_amount_from_the_raw_voucher(): void {
        $expense = $this->voucher('5001', ['voucher_type' => 'C', 'direction' => 'outgoing', 'payload' => ['sumGross' => '59.50', 'paidAmount' => 20]]);
        $income = $this->voucher('5002', ['voucher_type' => 'D', 'direction' => 'incoming', 'payload' => ['sumGross' => '119.00']]);
        $legacy = $this->voucher('5003', ['voucher_type' => 'C', 'direction' => null]);
        $invoice = $this->voucher('invoice:9001', ['voucher_type' => 'RE', 'direction' => 'outgoing', 'document_kind' => 'invoice']);
        $easybill = $this->voucher('7001', ['plugin_id' => 'easybill', 'voucher_type' => 'D', 'direction' => 'incoming']);

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame(['incoming', 'other', '39.50'], [$expense->fresh()?->direction, $expense->fresh()?->document_kind, (string) $expense->fresh()?->open_amount]);
        $this->assertSame('outgoing', $income->fresh()?->direction);
        $this->assertNull($income->fresh()?->open_amount, 'Ohne paidAmount bleibt der offene Betrag leer');
        $this->assertSame('incoming', $legacy->fresh()?->direction);
        $this->assertSame('outgoing', $invoice->fresh()?->direction);
        $this->assertSame('incoming', $easybill->fresh()?->direction, 'Andere Programme bleiben unberührt');
    }
}
