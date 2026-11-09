<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MirroredVoucherPdfTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins;

use App\Models\Finance\AccountingVoucher;
use App\Models\Platform\{Organization, PluginSetting, User};
use App\Plugins\Easybill\EasybillPlugin;
use App\Plugins\SevDesk\SevDeskPlugin;
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Original-PDF gespiegelter Belege (MVP-1499): sevDesk über
 * `GET /Invoice/{id}/getPdf` ohne Versandvermerk, easybill über
 * `GET /documents/{id}/pdf`; Recht, Organisation und Belegart werden geprüft.
 */
final class MirroredVoucherPdfTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
    }

    private function enable(string $pluginId): void {
        PluginSetting::query()->create(['organization_id' => $this->organization->id, 'plugin_id' => $pluginId, 'enabled' => true, 'settings' => ['api_key' => 'k-' . $pluginId]]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function voucher(string $pluginId, string $externalId, array $attributes = []): AccountingVoucher {
        return AccountingVoucher::query()->withoutGlobalScopes()->create($attributes + [
            'organization_id' => $this->organization->id, 'plugin_id' => $pluginId, 'external_id' => $externalId,
            'direction' => 'outgoing', 'document_kind' => 'invoice', 'voucher_type' => 'invoice', 'voucher_state' => 'open',
            'voucher_number' => 'RE-2026-77', 'voucher_date' => '2026-10-31', 'total_amount' => '214.20', 'currency' => 'EUR', 'archived' => false,
        ]);
    }

    public function test_sevdesk_invoice_pdf_is_fetched_without_marking_it_sent(): void {
        $this->enable(SevDeskPlugin::ID);
        $voucher = $this->voucher(SevDeskPlugin::ID, 'invoice:77');
        $fake = FakePluginHttp::fake([
            'https://my.sevdesk.de/api/v1/Invoice/77/getPdf*' => FakePluginHttp::response(['objects' => ['filename' => 'RE-2026-77.pdf', 'mimeType' => 'application/pdf', 'base64encoded' => true, 'content' => base64_encode("%PDF-1.4\n%%EOF\n")]]),
        ]);

        $response = $this->actingAs($this->admin)->get(route('finance.sevdesk.vouchers.pdf', Sqid::encode(AccountingVoucher::class, (int) $voucher->id)));

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame("%PDF-1.4\n%%EOF\n", $response->getContent());
        $this->assertStringContainsString('sevdesk-re-2026-77.pdf', (string) $response->headers->get('Content-Disposition'));
        $fake->assertSent(static function (RequestInterface $request): bool {
            parse_str($request->getUri()->getQuery(), $query);

            return str_ends_with($request->getUri()->getPath(), '/Invoice/77/getPdf') && ($query['preventSendBy'] ?? null) === 'true';
        });
    }

    public function test_sevdesk_credit_note_pdf_comes_from_the_credit_note_resource(): void {
        $this->enable(SevDeskPlugin::ID);
        $voucher = $this->voucher(SevDeskPlugin::ID, 'creditnote:701', ['document_kind' => 'credit_note', 'voucher_type' => 'CreditNote', 'voucher_number' => 'GU-1001']);
        $fake = FakePluginHttp::fake([
            'https://my.sevdesk.de/api/v1/CreditNote/701/getPdf*' => FakePluginHttp::response(['objects' => ['filename' => 'GU-1001.pdf', 'mimeType' => 'application/pdf', 'base64encoded' => true, 'content' => base64_encode("%PDF-1.4 Gutschrift\n")]]),
        ]);

        $response = $this->actingAs($this->admin)->get(route('finance.sevdesk.vouchers.pdf', Sqid::encode(AccountingVoucher::class, (int) $voucher->id)));

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame("%PDF-1.4 Gutschrift\n", $response->getContent());
        $this->assertStringContainsString('sevdesk-gu-1001.pdf', (string) $response->headers->get('Content-Disposition'));
        $fake->assertSent(static function (RequestInterface $request): bool {
            parse_str($request->getUri()->getQuery(), $query);

            return str_ends_with($request->getUri()->getPath(), '/CreditNote/701/getPdf') && ($query['preventSendBy'] ?? null) === 'true';
        });
    }

    public function test_easybill_document_pdf_is_streamed(): void {
        $this->enable(EasybillPlugin::ID);
        $voucher = $this->voucher(EasybillPlugin::ID, '55001');
        FakePluginHttp::fake([
            'https://api.easybill.de/rest/v1/documents/55001/pdf' => FakePluginHttp::response('%PDF-1.4 easybill', 200, ['Content-Type' => 'application/pdf']),
        ]);

        $response = $this->actingAs($this->admin)->get(route('finance.easybill.vouchers.pdf', Sqid::encode(AccountingVoucher::class, (int) $voucher->id)));

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame('%PDF-1.4 easybill', $response->getContent());
    }

    public function test_pdf_routes_check_permission_organization_and_document_kind(): void {
        $this->enable(SevDeskPlugin::ID);
        $this->enable(EasybillPlugin::ID);
        FakePluginHttp::fake([
            'https://my.sevdesk.de/api/v1/Invoice/*' => FakePluginHttp::response(['objects' => ['content' => base64_encode('%PDF')]]),
            'https://api.easybill.de/rest/v1/documents/*' => FakePluginHttp::response('%PDF', 200, ['Content-Type' => 'application/pdf']),
        ]);
        $invoice = $this->voucher(SevDeskPlugin::ID, 'invoice:78');
        $receipt = $this->voucher(SevDeskPlugin::ID, '901', ['direction' => 'incoming', 'document_kind' => 'other', 'voucher_type' => 'C']);
        $template = $this->voucher(EasybillPlugin::ID, '55002', ['document_kind' => 'recurring_template', 'direction' => 'neutral']);
        $foreign = $this->voucher(SevDeskPlugin::ID, 'invoice:79', ['organization_id' => Organization::factory()->create()->id]);
        $key = static fn (AccountingVoucher $voucher): string => Sqid::encode(AccountingVoucher::class, (int) $voucher->id);

        $clerk = $this->orgUser();
        $this->assertFalse($clerk->can('voucher.viewAny'));
        $this->actingAs($clerk)->get(route('finance.sevdesk.vouchers.pdf', $key($invoice)))->assertForbidden();

        $this->actingAs($this->admin)->get(route('finance.sevdesk.vouchers.pdf', $key($receipt)))->assertNotFound();
        $this->actingAs($this->admin)->get(route('finance.easybill.vouchers.pdf', $key($template)))->assertNotFound();
        $this->actingAs($this->admin)->get(route('finance.sevdesk.vouchers.pdf', $key($foreign)))->assertNotFound();
        // Fremdes Programm über die falsche Route.
        $this->actingAs($this->admin)->get(route('finance.easybill.vouchers.pdf', $key($invoice)))->assertNotFound();

        PluginSetting::query()->where('plugin_id', SevDeskPlugin::ID)->firstOrFail()->update(['settings' => ['api_key' => '']]);
        $this->actingAs($this->admin)->get(route('finance.sevdesk.vouchers.pdf', $key($invoice)))->assertNotFound();
    }
}
