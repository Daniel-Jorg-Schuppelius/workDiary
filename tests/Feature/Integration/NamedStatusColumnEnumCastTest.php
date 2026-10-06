<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NamedStatusColumnEnumCastTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Enums\Finance\AccountingVoucherState;
use App\Enums\Gaeb\GaebPhase;
use App\Enums\Integration\ExternalArticleSyncStatus;
use App\Models\Article\Article;
use App\Models\Finance\AccountingVoucher;
use App\Models\Gaeb\{BillOfQuantity, BoqItem};
use App\Models\Integration\ExternalArticleMapping;
use App\Services\Gaeb\BoqDocumentFactory;
use ERechnungToolkit\Enums\GaebAlternativeBidStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Konsolidierungs-Audit 2026-10, k3-10 (benannte Statusspalten): Spalten, die
 * nur ein Importer oder Spiegel schreibt, haben keinen eigenen Bereichstest.
 * Hier steht je Spalte, was nach dem Enum-Cast laut oder still bräche.
 */
final class NamedStatusColumnEnumCastTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /** Die Artikelseite verkettete den Stand mit dem Textschlüssel — mit einem Enum ein harter Fehler. */
    public function test_article_page_labels_the_sync_status_of_external_mappings(): void {
        app()->setLocale('de');
        $article = Article::factory()->create(['organization_id' => $this->organization->id]);
        foreach (ExternalArticleSyncStatus::cases() as $status) {
            ExternalArticleMapping::query()->create([
                'organization_id' => $this->organization->id,
                'plugin_id' => 'p-' . $status->value,
                'external_id' => 'ext-' . $status->value,
                'article_id' => $article->id,
                'sync_status' => $status,
            ]);
        }
        $default = ExternalArticleMapping::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'p-vorgabe',
            'external_id' => 'ext-vorgabe',
            'article_id' => $article->id,
        ]);
        $this->assertSame(ExternalArticleSyncStatus::Pending, $default->fresh()->sync_status);

        $html = (string) $this->actingAs($this->orgAdmin())->get(route('articles.show', $article))->assertOk()->getContent();

        foreach (['pending' => 'Ausstehend', 'synced' => 'Synchronisiert', 'linked' => 'Verknüpft'] as $value => $label) {
            $this->assertStringContainsString('<td class="font-mono">ext-' . $value . '</td><td>' . $label . '</td>', $html);
        }
    }

    public function test_boq_item_carries_the_side_bid_status_into_the_gaeb_document(): void {
        $boq = BillOfQuantity::factory()->create(['organization_id' => $this->organization->id]);
        $plain = BoqItem::factory()->create(['organization_id' => $this->organization->id, 'bill_of_quantity_id' => $boq->id, 'position' => 1]);
        $modified = BoqItem::factory()->create([
            'organization_id' => $this->organization->id,
            'bill_of_quantity_id' => $boq->id,
            'position' => 2,
            'alternative_bid_status' => GaebAlternativeBidStatus::NotRequired,
        ]);

        $this->assertNull($plain->fresh()->alternative_bid_status);
        $this->assertSame(GaebAlternativeBidStatus::NotRequired, $modified->fresh()->alternative_bid_status);
        $this->assertDatabaseHas('boq_items', ['id' => $modified->id, 'alternative_bid_status' => 'N/A']);

        $items = app(BoqDocumentFactory::class)->fromModel($boq->fresh(), GaebPhase::SideBid)->getItems();
        $this->assertSame(
            [null, GaebAlternativeBidStatus::NotRequired],
            array_map(static fn ($item): ?GaebAlternativeBidStatus => $item->getAlternativeBidStatus(), $items),
        );
    }

    /** Zeilen aus der Zeit vor dem normalisierten Zustand tragen NULL und bleiben ladbar. */
    public function test_accounting_voucher_state_is_nullable_and_cast(): void {
        $legacy = AccountingVoucher::factory()->create(['organization_id' => $this->organization->id]);
        $paid = AccountingVoucher::factory()->create(['organization_id' => $this->organization->id, 'voucher_state' => 'paid', 'voucher_status' => '1000']);

        $this->assertNull($legacy->fresh()->voucher_state);
        $this->assertSame(AccountingVoucherState::Paid, $paid->fresh()->voucher_state);
        $this->assertSame('1000', $paid->fresh()->voucher_status, 'Der Rohstatus des Fremdsystems bleibt eine Zeichenkette.');
        $this->assertDatabaseHas('accounting_vouchers', ['id' => $paid->id, 'voucher_state' => AccountingVoucherState::Paid->value]);
    }
}
