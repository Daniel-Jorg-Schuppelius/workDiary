<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceShowToolbarTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Customer\Customer;
use App\Models\Invoicing\{Invoice, InvoiceItem};
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Seitenkopf der Rechnung (MVP-968): Hauptaktion je Status fest, Destruktives
 * im ⋯-Menü, Ausgabeformate unter „Export", Positionen an der Tabelle.
 */
class InvoiceShowToolbarTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME',
            'currency' => 'EUR',
            'created_by' => $this->admin->id,
        ]);
    }

    private function invoice(InvoiceStatus|string $status): Invoice {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'status' => $status,
        ]);
        InvoiceItem::factory()->create(['organization_id' => $this->organization->id, 'invoice_id' => $invoice->id]);

        return $invoice;
    }

    /** Öffnendes Tag des Knopfs, dessen Beschriftung $label ist. */
    private function buttonTag(string $html, string $label): string {
        $this->assertMatchesRegularExpression('~<(?:button|a)\b[^>]*>(?:(?!</(?:button|a)>).)*<span>' . preg_quote($label, '~') . '</span>~s', $html, "Knopf „{$label}“ fehlt");
        preg_match_all('~<(?:button|a)\b[^>]*>(?:(?!</(?:button|a)>).)*?<span>' . preg_quote($label, '~') . '</span>~s', $html, $m);
        $element = end($m[0]);
        $this->assertIsString($element);

        return substr($element, 0, (int) strpos($element, '>') + 1);
    }

    private function page(Invoice $invoice): string {
        $html = $this->actingAs($this->admin)->get(route('invoices.show', $invoice))->assertOk()->getContent();
        $this->assertIsString($html);

        return $html;
    }

    private function toolbar(string $html): string {
        return substr($html, (int) strpos($html, 'data-toolbar>'), (int) strpos($html, 'data-toolbar-menu-danger') - (int) strpos($html, 'data-toolbar>'));
    }

    public function test_draft_pins_issuing_and_moves_rare_and_destructive_actions_into_the_menu(): void {
        $html = $this->page($this->invoice(InvoiceStatus::Draft));

        $this->assertStringContainsString('data-toolbar-placement="bar"', $this->buttonTag($html, 'Stellen'));
        $this->assertStringContainsString('data-toolbar-placement="danger"', $this->buttonTag($html, 'Löschen'));
        $this->assertStringContainsString('data-toolbar-placement="menu"', $this->buttonTag($html, 'Konditionen'));
        $this->assertStringContainsString('data-toolbar-placement="menu"', $this->buttonTag($html, __('invoicing.retention.action')));
    }

    public function test_draft_adds_positions_at_the_table_not_in_the_page_header(): void {
        $html = $this->page($this->invoice(InvoiceStatus::Draft));

        $this->assertStringNotContainsString('Position hinzufügen', $this->toolbar($html));
        $card = substr($html, (int) strpos($html, '>' . __('Positionen') . '<'));
        $this->assertMatchesRegularExpression('~data-action-menu-items>.*Position hinzufügen.*Spesen hinzufügen~s', $card);
    }

    public function test_issued_invoice_pins_payment_and_bundles_the_formats(): void {
        $html = $this->page($this->invoice(InvoiceStatus::Issued));

        $this->assertStringContainsString('data-toolbar-placement="bar"', $this->buttonTag($html, 'Bezahlt markieren'));
        $this->assertStringContainsString('data-toolbar-placement="danger"', $this->buttonTag($html, 'Stornieren'));
        $this->assertMatchesRegularExpression('~<span>Export</span>.*data-action-menu-items>.*' . preg_quote(__('invoicing.einvoice.button'), '~') . '~s', $html);
        $this->assertStringNotContainsString('Position hinzufügen', $html);
    }
}
