<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EasybillVoucherPullTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Easybill;

use App\Enums\Billing\{DocumentDirection, DocumentKind};
use App\Models\{Customer, ExternalReference, PluginSetting};
use App\Models\Finance\AccountingVoucher;
use App\Plugins\Easybill\EasybillPlugin;
use App\Plugins\Easybill\Services\EasybillVoucherPullService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-731 (Vollscan G18): Beleg-Rückabruf aus easybill.
 *
 * Die Antworten sind aufgezeichnete Beispielantworten nach der öffentlichen
 * Swagger-Fixture (tests/Fixtures/Plugins/Easybill/openapi.json) — kein
 * Netzzugriff, alles über den Guzzle-MockHandler der FakePluginHttp.
 */
class EasybillVoucherPullTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const DOCS = 'https://api.easybill.de/rest/v1/documents*';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        PluginSetting::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => EasybillPlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => 'eb-key'],
        ]);

        // Seitengröße klein halten, damit die Fixtures zwei echte Seiten sind.
        config()->set('plugins.easybill.page_size', 2);
    }

    /** @return array<string, mixed> */
    private function fixture(string $name): array {
        /** @var array<string, mixed> $data */
        $data = json_decode((string) file_get_contents(base_path('tests/Fixtures/Plugins/Easybill/' . $name)), true);

        return $data;
    }

    private function fakePages(): FakePluginHttp {
        return FakePluginHttp::fake([
            self::DOCS => [
                FakePluginHttp::response($this->fixture('documents.page1.json')),
                FakePluginHttp::response($this->fixture('documents.page2.json')),
                FakePluginHttp::response(['page' => 3, 'pages' => 2, 'limit' => 2, 'total' => 3, 'items' => []]),
            ],
        ]);
    }

    public function test_maps_documents_including_storno_and_paginates(): void {
        $fake = $this->fakePages();

        $result = app(EasybillVoucherPullService::class)->pull((int) $this->organization->id);

        $this->assertSame(['read' => 3, 'created' => 3, 'updated' => 0, 'skipped' => 0], $result);

        // Nur buchhalterisch relevante Belegarten werden abgefragt.
        $fake->assertSent(fn (RequestInterface $r): bool => str_contains(
            urldecode((string) $r->getUri()),
            'type=INVOICE,CREDIT,STORNO,STORNO_CREDIT',
        ));

        $open = AccountingVoucher::query()->where('external_id', '4711')->firstOrFail();
        $this->assertSame(EasybillPlugin::ID, $open->plugin_id);
        $this->assertSame(DocumentDirection::Outgoing->value, $open->direction);
        $this->assertSame(DocumentKind::Invoice->value, $open->document_kind);
        $this->assertSame('open', $open->voucher_state);
        // easybill liefert CENTS — 119000 sind 1190,00 €, nicht 119.000 €.
        $this->assertSame('1190.00', (string) $open->total_amount);
        $this->assertSame('1000.00', (string) $open->net_amount);
        $this->assertSame('1190.00', (string) $open->open_amount);
        $this->assertFalse($open->is_cancellation);

        $paid = AccountingVoucher::query()->where('external_id', '4712')->firstOrFail();
        $this->assertSame('paid', $paid->voucher_state);
        $this->assertSame('0.00', (string) $paid->open_amount);
        $this->assertSame('2026-08-10', $paid->paid_date?->toDateString());

        $storno = AccountingVoucher::query()->where('external_id', '4713')->firstOrFail();
        $this->assertTrue($storno->is_cancellation);
        $this->assertSame(DocumentKind::Cancellation->value, $storno->document_kind);
        $this->assertSame('4711', $storno->cancels_external_id);
        $this->assertSame('-1190.00', (string) $storno->total_amount);
    }

    public function test_second_run_is_incremental_and_updates_instead_of_duplicating(): void {
        $this->fakePages();
        app(EasybillVoucherPullService::class)->pull((int) $this->organization->id);
        $this->assertSame(3, AccountingVoucher::query()->count());

        $fake = FakePluginHttp::fake([
            self::DOCS => [
                FakePluginHttp::response($this->fixture('documents.page1.json')),
                FakePluginHttp::response(['page' => 2, 'pages' => 1, 'limit' => 2, 'total' => 2, 'items' => []]),
            ],
        ]);

        $result = app(EasybillVoucherPullService::class)->pull((int) $this->organization->id);

        // Dubletten fängt der Unique-Key (Org + Plugin + external_id).
        $this->assertSame(0, $result['created']);
        $this->assertSame(2, $result['updated']);
        $this->assertSame(3, AccountingVoucher::query()->count());

        // Inkrement über den Änderungsmarker des Fremdsystems (edited_at).
        $fake->assertSent(fn (RequestInterface $r): bool => str_contains(
            urldecode((string) $r->getUri()),
            'edited_at=2026-08-12',
        ));
    }

    public function test_links_the_customer_via_the_pushed_contact_reference(): void {
        $customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME',
            'number' => 'K-1',
            'created_by' => $this->orgAdmin()->id,
        ]);
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => EasybillPlugin::ID,
            'external_type' => 'contact',
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->getKey(),
            'external_id' => '9001',
        ]);

        $this->fakePages();
        app(EasybillVoucherPullService::class)->pull((int) $this->organization->id);

        $voucher = AccountingVoucher::query()->where('external_id', '4711')->firstOrFail();
        $this->assertSame($customer->getKey(), $voucher->customer_id);
        $this->assertNull($voucher->supplier_id);
    }

    public function test_without_api_key_nothing_is_requested(): void {
        // Über das Modell, damit der encrypted-Cast greift (roher
        // Query-Builder-Update legt Klartext in eine encrypted-Spalte).
        PluginSetting::query()->firstOrFail()->update(['settings' => ['api_key' => '']]);
        $fake = FakePluginHttp::fake([self::DOCS => FakePluginHttp::response([])]);

        $result = app(EasybillVoucherPullService::class)->pull((int) $this->organization->id);

        $this->assertSame(0, $result['read']);
        $fake->assertNothingSent();
    }
}
