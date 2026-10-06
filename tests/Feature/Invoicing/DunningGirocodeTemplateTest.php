<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DunningGirocodeTemplateTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Invoicing\InvoiceStatus;
use App\Mail\DunningMail;
use App\Models\Customer\Customer;
use App\Models\Invoicing\{Invoice, InvoiceMailTemplate};
use App\Models\Platform\{Organization, User};
use App\Services\Invoicing\{DunningPdfRenderer, GirocodeService};
use App\Services\UI\BrandingService;
use App\Settings\SettingScope;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MVP-997: Girocode über die Gesamtforderung der Mahnung, Mahn-Mail als Vorlagenart. */
final class DunningGirocodeTemplateTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private Invoice $invoice;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create(['settings' => ['branding' => ['legal' => [
            'iban' => 'DE02120300000000202051', 'bic' => 'BYLADEM1001', 'bank_name' => 'Testbank', 'account_holder' => 'ACME GmbH',
        ]]]]);
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        Setting::set('invoicing.girocode_enabled', true, SettingScope::Organization, $this->org);

        $this->invoice = Invoice::create([
            'organization_id' => $this->org->id, 'customer_id' => Customer::factory()->create(['organization_id' => $this->org->id])->id,
            'number' => 'R2030-0042', 'status' => InvoiceStatus::Issued, 'currency' => 'EUR', 'tax_rate' => '19.00',
            'issued_on' => now()->subDays(40), 'due_on' => now()->subDays(26), 'created_by' => $this->admin->id,
        ]);
        $this->invoice->items()->create(['organization_id' => $this->org->id, 'description' => 'Beratung', 'quantity' => '1.000', 'unit_price' => '100.0000', 'tax_rate' => '19.00', 'position' => 1]);
        $this->invoice->load('items');
        $this->invoice->recalculate();
        $this->invoice->save();
    }

    public function test_dunning_letter_carries_a_girocode_over_the_claim_total(): void {
        $interest = ['rate' => 9.12, 'days' => 26, 'amount' => 0.77];
        $data = app(DunningPdfRenderer::class)->viewData($this->invoice, 2, null, 5.0, null, $interest);

        $this->assertSame(124.77, $data['claimTotal']);
        $this->assertNotNull($data['girocode']);
        $payload = (string) app(GirocodeService::class)->payload($this->invoice, app(BrandingService::class)->legalFor($this->org), $data['claimTotal']);
        $this->assertStringContainsString('EUR124.77', $payload);
        $this->assertStringContainsString('R2030-0042', $payload);
        $this->assertStringContainsString('data:image/svg+xml', view('invoices.dunning-pdf', $data)->render());

        $this->invoice->forceFill(['status' => InvoiceStatus::Paid])->save();
        $this->assertNull(app(DunningPdfRenderer::class)->viewData($this->invoice->refresh(), 2, null, 5.0)['girocode'], 'bezahlt: kein Code');
    }

    public function test_dunning_mail_uses_an_organisation_template_and_keeps_the_standard_text_otherwise(): void {
        $standard = new DunningMail($this->invoice, 1);
        $this->assertSame(__('Zahlungserinnerung zur Rechnung :number', ['number' => 'R2030-0042']), $standard->subjectLine());

        $this->actingAs($this->admin)->post(route('admin.invoice-mail-templates.store'), [
            'name' => 'Mahnung', 'document_kind' => 'dunning', 'is_default' => '1',
            'subject' => '{{dunning_label}} {{invoice_number}}',
            'body_html' => '<p>Bitte zahlen Sie {{claim_total}} {{currency}} bis {{pay_until}}.</p><p>{{custom_text}}</p>',
            'body_text' => 'Bitte zahlen Sie {{claim_total}} {{currency}} bis {{pay_until}}. {{custom_text}}',
        ])->assertRedirect();
        $this->assertSame('dunning', InvoiceMailTemplate::query()->sole()->document_kind);

        $mail = new DunningMail($this->invoice, 2, 'Letzte Frist.', null, 5.0, now()->addDays(7)->toImmutable());
        $this->assertSame(__(':level. Mahnung', ['level' => 2]) . ' R2030-0042', $mail->subjectLine());
        $text = (string) $mail->content()->with['text'];
        $this->assertStringContainsString('Bitte zahlen Sie 124,00 EUR bis ' . now()->addDays(7)->format('d.m.Y'), $text);
        $this->assertStringContainsString('Letzte Frist.', $text);
    }
}
