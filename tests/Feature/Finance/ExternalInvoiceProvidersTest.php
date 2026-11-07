<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalInvoiceProvidersTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{ExternalInvoiceFormat, ExternalInvoiceStatus, ProviderSendState};
use App\Models\Platform\PluginSetting;
use App\Plugins\Easybill\EasybillPlugin;
use App\Plugins\Easybill\Services\EasybillExternalInvoiceProvider;
use App\Plugins\OrgaMax\Enums\OrgaMaxConnectionStatus;
use App\Plugins\OrgaMax\Models\OrgaMaxConnection;
use App\Plugins\OrgaMax\Services\OrgaMaxExternalInvoiceProvider;
use App\Plugins\SevDesk\Services\SevDeskExternalInvoiceProvider;
use App\Plugins\SevDesk\SevDeskPlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Anbieter der externen Rechnungshoheit (Feature 196) gegen ihre dokumentierten
 * Antworten: Stand, Positionen, Versandvermerk und der Abruf des Originals in
 * der richtigen Fassung.
 */
final class ExternalInvoiceProvidersTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_sevdesk_reports_sending_and_never_marks_the_pdf_download_as_sent(): void {
        PluginSetting::create(['organization_id' => $this->organization->id, 'plugin_id' => SevDeskPlugin::ID, 'enabled' => true, 'settings' => ['api_key' => 'k']]);
        $fake = FakePluginHttp::fake([
            'https://my.sevdesk.de/api/v1/Invoice/77/getPositions*' => FakePluginHttp::response(['objects' => [['name' => 'Wartung', 'quantity' => '2', 'priceNet' => '90', 'sumNetAccounting' => '180']]]),
            'https://my.sevdesk.de/api/v1/Invoice/77/getPdf*' => FakePluginHttp::response(['objects' => ['filename' => 'RE-1001.pdf', 'mimeType' => 'application/pdf', 'base64encoded' => true, 'content' => base64_encode("%PDF-1.4\n%%EOF\n")]]),
            'https://my.sevdesk.de/api/v1/Invoice/77/getXml' => FakePluginHttp::response(['objects' => '<?xml version="1.0"?><Invoice/>']),
            'https://my.sevdesk.de/api/v1/Invoice/77' => FakePluginHttp::response(['objects' => [[
                'id' => '77', 'status' => '200', 'invoiceNumber' => 'RE-1001', 'invoiceDate' => '2026-10-31T00:00:00+01:00',
                'sumNet' => '180', 'sumGross' => '214.2', 'currency' => 'EUR', 'sendDate' => '2026-11-01T10:00:00+01:00', 'sendType' => 'VM',
            ]]]),
        ]);
        $provider = app(SevDeskExternalInvoiceProvider::class);

        $snapshot = $provider->fetch($this->organization->id, '77');

        $this->assertSame(ExternalInvoiceStatus::Open, $snapshot->status);
        $this->assertSame('RE-1001', $snapshot->number);
        $this->assertSame(ProviderSendState::Sent, $snapshot->sent);
        $this->assertSame([['name' => 'Wartung', 'quantity' => '2.000', 'unit_price' => '90.0000', 'net' => '180.00']], $snapshot->lines);

        $pdf = $provider->original($this->organization->id, $snapshot);
        $this->assertSame("%PDF-1.4\n%%EOF\n", $pdf?->content);
        $fake->assertSent(function (RequestInterface $request): bool {
            parse_str($request->getUri()->getQuery(), $query);

            return str_ends_with($request->getUri()->getPath(), '/Invoice/77/getPdf') && ($query['preventSendBy'] ?? null) === 'true';
        });
        $this->assertSame('application/xml', $provider->original($this->organization->id, $snapshot, preferXml: true)?->mime);
    }

    public function test_easybill_converts_cents_and_requests_the_xrechnung_explicitly(): void {
        PluginSetting::create(['organization_id' => $this->organization->id, 'plugin_id' => EasybillPlugin::ID, 'enabled' => true, 'settings' => ['api_key' => 'k']]);
        $fake = FakePluginHttp::fake([
            'https://api.easybill.de/rest/v1/documents/55/download' => FakePluginHttp::response('<?xml version="1.0"?><Invoice/>', 200, ['Content-Type' => 'application/xml;format=xrechnung;version=3.0.1']),
            'https://api.easybill.de/rest/v1/documents/55' => FakePluginHttp::response([
                'id' => 55, 'is_draft' => false, 'number' => 'RE-55', 'document_date' => '2026-10-31', 'currency' => 'EUR',
                'amount' => 21420, 'amount_net' => 18000, 'cancel_id' => null, 'paid_at' => null, 'type' => 'INVOICE',
                'file_format_config' => [['type' => 'xrechnung3_0_xml']],
                'items' => [['type' => 'POSITION', 'description' => "Wartung\nServerraum", 'quantity' => 2, 'single_price_net' => 9000, 'total_price_net' => 18000]],
            ]),
        ]);
        $provider = app(EasybillExternalInvoiceProvider::class);

        $snapshot = $provider->fetch($this->organization->id, '55');

        $this->assertSame(ExternalInvoiceStatus::Open, $snapshot->status);
        $this->assertSame('180.00', $snapshot->netAmount);
        $this->assertSame('214.20', $snapshot->grossAmount);
        $this->assertSame(ExternalInvoiceFormat::XRechnungUbl, $snapshot->formatHint);
        $this->assertSame('Wartung', $snapshot->lines[0]['name']);
        $this->assertSame('180.00', $snapshot->lines[0]['net']);
        $this->assertSame('application/xml', $provider->original($this->organization->id, $snapshot)?->mime);
        $fake->assertSent(fn (RequestInterface $request): bool => str_ends_with((string) $request->getUri(), '/documents/55/download') && $request->getHeaderLine('Accept') === 'application/xml');
    }

    public function test_orgamax_maps_the_invoice_and_fetches_the_pdf(): void {
        OrgaMaxConnection::create([
            'organization_id' => $this->organization->id,
            'mode' => OrgaMaxConnection::MODE_PRIVATE,
            'api_key' => 'k',
            'api_secret' => 's',
            'ownership_id' => 'own-1',
            'bearer_token' => 'token',
            'token_expires_at' => Carbon::now()->addHour(),
            'status' => OrgaMaxConnectionStatus::Active,
            'capabilities' => ['billing' => ['enabled' => true, 'leader' => 'orgamax']],
        ]);
        $fake = FakePluginHttp::fake([
            'https://api.orgamax.de/openapi/invoice/document/9*' => FakePluginHttp::response("%PDF-1.4\n%%EOF\n", 200, ['Content-Type' => 'application/pdf']),
            'https://api.orgamax.de/openapi/invoice/9' => FakePluginHttp::response(['meta' => [], 'data' => [
                'id' => 9, 'number' => 'R-2026-9', 'date' => '2026-10-31', 'state' => 'locked', 'totalNet' => 180.0, 'totalGross' => 214.2,
                'positions' => [['title' => 'Wartung', 'amount' => 2, 'priceNet' => 90.0, 'totalNet' => 180.0]],
            ]]),
        ]);
        $provider = app(OrgaMaxExternalInvoiceProvider::class);

        $snapshot = $provider->fetch($this->organization->id, '9');

        $this->assertSame(ExternalInvoiceStatus::Open, $snapshot->status);
        $this->assertSame('R-2026-9', $snapshot->number);
        $this->assertSame([['name' => 'Wartung', 'quantity' => '2.000', 'unit_price' => '90.0000', 'net' => '180.00']], $snapshot->lines);
        $this->assertSame("%PDF-1.4\n%%EOF\n", $provider->original($this->organization->id, $snapshot)?->content);
        $fake->assertSent(fn (RequestInterface $request): bool => str_contains((string) $request->getUri(), '/invoice/document/9'));
    }
}
